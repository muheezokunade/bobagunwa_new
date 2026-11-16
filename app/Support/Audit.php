<?php

namespace App\Support;

use App\Models\AuditEvent;
use App\Models\SecurityEvent;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only audit and security logging. Context is redacted: secrets,
 * tokens and passwords never reach the log, and personal data is kept to the
 * minimum needed to explain the action.
 */
final class Audit
{
    private const REDACT = ['password', 'password_confirmation', 'token', 'raw_token', 'status_token', 'secret', 'activation_url', 'reset_url', '_token'];

    public static function record(string $action, string $summary, ?object $subject = null, array $context = [], ?int $actorId = null): AuditEvent
    {
        return AuditEvent::create([
            'actor_user_id' => $actorId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'summary' => mb_substr($summary, 0, 255),
            'context' => $context ? self::redact($context) : null,
            'ip_hash' => self::ipHash(),
            'request_id' => app()->bound('request_id') ? app('request_id') : null,
        ]);
    }

    public static function security(string $event, ?int $userId = null, array $context = []): void
    {
        SecurityEvent::create([
            'user_id' => $userId,
            'event' => $event,
            'ip_hash' => self::ipHash(),
            'context' => $context ? self::redact($context) : null,
        ]);
    }

    public static function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACT, true)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $context;
    }

    /** Keyed hash of the client address: lets officers correlate abuse without storing raw IPs. */
    public static function ipHash(?string $ip = null): ?string
    {
        $ip ??= app()->runningInConsole() ? null : request()->ip();

        return $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
    }
}
