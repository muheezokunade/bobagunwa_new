<?php

namespace App\Services\Finance;

use App\Exceptions\DomainRuleException;
use App\Models\User;
use App\Support\Tokens;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Shared guards for financial mutations.
 */
final class Ledger
{
    public static function authorise(User $user, string $permission, string $action): void
    {
        if (! $user->hasPermission($permission)) {
            throw new DomainRuleException("You do not have permission to {$action}.");
        }
    }

    /**
     * Run a mutation and translate database safeguard refusals (SQLSTATE 45000
     * "LEDGER:" signals, CHECK and foreign-key violations) into clear errors.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function guard(Closure $work): mixed
    {
        try {
            return $work();
        } catch (QueryException $e) {
            $message = $e->getMessage();
            if (preg_match('/(LEDGER|REGISTER): ([^(]+?)(?: \(Connection|$)/', $message, $m)) {
                throw new DomainRuleException(ucfirst(trim($m[2])).'.', 0, $e);
            }
            if ($e instanceof UniqueConstraintViolationException) {
                throw new DomainRuleException(str_contains($message, 'external_reference')
                    ? 'A payment with this bank or receipt reference is already recorded.'
                    : 'This record already exists, so nothing was duplicated.', 0, $e);
            }
            if (str_contains($message, 'CONSTRAINT') && str_contains($message, 'Check constraint')) {
                throw new DomainRuleException('The amount or values entered are not allowed.', 0, $e);
            }
            throw $e;
        }
    }

    public static function reference(string $prefix): string
    {
        return Tokens::reference($prefix, 7);
    }
}
