<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\Totp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Optional TOTP two-factor sign-in for privileged officers (see
 * docs/32-two-factor-authentication.md). Enforcement is off by default and
 * cannot be switched on while any privileged officer is unenrolled.
 */
class TwoFactorService
{
    /** Permissions that make an officer "privileged" for two-factor purposes. */
    public const PRIVILEGED = [
        'access.manage', 'settings.manage', 'backups.manage',
        'finance.assessments.manage', 'finance.payments.record', 'finance.payments.verify',
        'finance.allocate', 'finance.adjust', 'finance.reverse', 'finance.import',
    ];

    public const RECOVERY_CODES = 8;

    public static function isPrivileged(User $user): bool
    {
        return $user->hasAnyPermission(...self::PRIVILEGED);
    }

    public static function enforced(): bool
    {
        return Settings::bool('security.require_two_factor');
    }

    /** @return Collection<int, User> */
    public static function privilegedOfficersWithoutTwoFactor(): Collection
    {
        return User::with('member')->where('status', 'active')->whereNull('two_factor_confirmed_at')
            ->whereHas('roles.permissions', fn ($q) => $q->whereIn('key', self::PRIVILEGED))
            ->get()->filter(fn (User $u) => $u->isActive())->values();
    }

    /** @return array{secret: string, uri: string} */
    public function begin(User $user): array
    {
        if ($user->two_factor_confirmed_at) {
            throw new DomainRuleException('Two-factor sign-in is already set up for your account.');
        }
        $secret = Totp::newSecret();
        session(['two_factor.pending_secret' => $secret]);

        return ['secret' => $secret, 'uri' => Totp::uri('Bobagunwa', $user->email, $secret)];
    }

    /** @return list<string> plain recovery codes, shown once */
    public function confirm(User $user, string $code): array
    {
        $secret = session('two_factor.pending_secret');
        if (! $secret) {
            throw new DomainRuleException('Start the set-up again: no pending secret was found.');
        }
        $step = Totp::match($secret, $code);
        if ($step === null) {
            Audit::security('two_factor.enrolment_code_rejected', $user->id);
            throw new DomainRuleException('That code is not correct. Check the time on your phone and try the newest code.');
        }
        $codes = $this->newRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $codes),
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
        ])->save();
        session()->forget('two_factor.pending_secret');
        Audit::security('two_factor.enabled', $user->id);
        Audit::record('two_factor.enabled', "Two-factor sign-in enabled for {$user->name}", $user, [], $user->id);

        return $codes;
    }

    /**
     * Verify a sign-in code (single use per time step) or a recovery code
     * (single use). Serialised with a row lock to stop parallel replay.
     */
    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            if (! $locked->two_factor_confirmed_at || ! $locked->two_factor_secret) {
                return false;
            }
            $step = Totp::match($locked->two_factor_secret, $code);
            if ($step !== null) {
                if ($locked->two_factor_last_step !== null && $step <= $locked->two_factor_last_step) {
                    Audit::security('two_factor.code_replay_rejected', $locked->id);

                    return false;
                }
                $locked->forceFill(['two_factor_last_step' => $step])->save();

                return true;
            }
            $normalised = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
            if (strlen($normalised) === 10) {
                $hash = hash('sha256', substr($normalised, 0, 5).'-'.substr($normalised, 5));
                $remaining = $locked->two_factor_recovery_codes ?? [];
                $index = array_search($hash, $remaining, true);
                if ($index !== false) {
                    unset($remaining[$index]);
                    $locked->forceFill(['two_factor_recovery_codes' => array_values($remaining)])->save();
                    Audit::security('two_factor.recovery_code_used', $locked->id, ['remaining' => count($remaining)]);

                    return true;
                }
            }

            return false;
        });
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user, string $code): array
    {
        if (! $this->verify($user, $code)) {
            throw new DomainRuleException('That code is not correct.');
        }
        $codes = $this->newRecoveryCodes();
        $user->fresh()->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $codes)])->save();
        Audit::security('two_factor.recovery_codes_regenerated', $user->id);

        return $codes;
    }

    public function disable(User $user, string $password, string $code): void
    {
        if (self::enforced() && self::isPrivileged($user)) {
            throw new DomainRuleException('Two-factor sign-in is required for your role and cannot be switched off.');
        }
        if (! Hash::check($password, (string) $user->password) || ! $this->verify($user, $code)) {
            Audit::security('two_factor.disable_rejected', $user->id);
            throw new DomainRuleException('Your password or code is not correct.');
        }
        $this->clear($user);
        Audit::security('two_factor.disabled', $user->id);
    }

    /** Lost device: another administrator removes enrolment; the officer must enrol again. */
    public function adminReset(User $target, User $actor, string $reason): void
    {
        if (! $actor->hasPermission('access.manage')) {
            throw new DomainRuleException('You do not have permission to reset two-factor sign-in.');
        }
        if ($actor->id === $target->id) {
            throw new DomainRuleException('You cannot reset your own two-factor sign-in. Ask another administrator.');
        }
        $this->clear($target);
        app(AccountService::class)->endAllSessions($target);
        Audit::security('two_factor.reset_by_admin', $target->id, ['by' => $actor->id]);
        Audit::record('two_factor.reset', "Two-factor sign-in reset for {$target->name}", $target, ['reason' => $reason]);
    }

    private function clear(User $user): void
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return array_map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)), range(1, self::RECOVERY_CODES));
    }
}
