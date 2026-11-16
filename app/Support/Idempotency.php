<?php

namespace App\Support;

use App\Exceptions\DomainRuleException;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Exactly-once execution for state-changing operations.
 *
 * The key row is inserted inside the same transaction as the work. A
 * concurrent duplicate blocks on the unique index until the first commits,
 * then fails and is answered with the original result (a replay). The same
 * key with a different payload is a conflict.
 */
final class Idempotency
{
    /**
     * @template T of Model
     *
     * @param  Closure(): T  $work
     * @return array{0: T, 1: bool} [result, replayed]
     */
    public static function run(string $scope, ?string $key, array $payload, Closure $work, string $modelClass): array
    {
        if ($key === null || ! preg_match('/^[A-Za-z0-9_-]{16,80}$/', $key)) {
            throw new DomainRuleException('This form has expired. Reload the page and try again.');
        }
        $hash = hash('sha256', json_encode($payload));

        try {
            return [DB::transaction(function () use ($scope, $key, $hash, $work) {
                $id = DB::table('idempotency_keys')->insertGetId([
                    'scope' => $scope, 'key' => $key, 'request_hash' => $hash,
                    'created_by' => auth()->id(), 'created_at' => now(),
                ]);
                $result = $work();
                DB::table('idempotency_keys')->where('id', $id)->update(['result_type' => class_basename($result), 'result_id' => $result->getKey()]);

                return $result;
            }), false];
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), 'idempotency_keys_scope_key_unique')) {
                throw $e;
            }
            $existing = DB::table('idempotency_keys')->where('scope', $scope)->where('key', $key)->first();
            if (! $existing || ! hash_equals($existing->request_hash, $hash) || $existing->result_id === null) {
                throw new DomainRuleException('This request was already submitted with different details. Reload the page before trying again.');
            }

            return [$modelClass::query()->findOrFail($existing->result_id), true];
        }
    }

    public static function newKey(): string
    {
        return Tokens::generate(24);
    }
}
