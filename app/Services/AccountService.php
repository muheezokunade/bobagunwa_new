<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\AccountToken;
use App\Models\Member;
use App\Models\OutboundMessage;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use App\Support\Normalize;
use App\Support\Tokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Account lifecycle: provisioning, single-use activation and reset tokens,
 * password changes and session invalidation.
 *
 * Raw tokens exist only in the return value of an issue call. The database
 * holds SHA-256 hashes; tokens are single-use, expire, and issuing a new token
 * revokes any earlier unused token for the same purpose.
 */
class AccountService
{
    public function __construct(private MessageService $messages) {}

    /** Create (or return) the pending account linked to an approved member. */
    public function provisionMemberAccount(Member $member): User
    {
        if ($member->user) {
            return $member->user;
        }
        $email = Normalize::email($member->email);
        if (! $email) {
            throw new DomainRuleException('Record an email address for this member before issuing account access.');
        }
        if (User::where('email', $email)->exists()) {
            throw new DomainRuleException('Another account already uses this email address. Resolve the duplicate before issuing access.');
        }
        $user = User::create(['name' => $member->full_name, 'email' => $email, 'member_id' => $member->id, 'status' => 'pending_activation']);
        $user->roles()->attach(Role::where('key', 'member')->value('id'), ['granted_by' => auth()->id(), 'reason' => 'Approved member account']);

        return $user;
    }

    /** @return array{token: string, url: string, record: AccountToken} */
    public function issue(User $user, string $purpose, string $via, ?int $issuedBy): array
    {
        if ($purpose === 'activation' && $user->status !== 'pending_activation') {
            throw new DomainRuleException('This account is already active. Issue a password reset instead.');
        }
        if ($purpose === 'password_reset' && $user->status !== 'active') {
            throw new DomainRuleException('Password resets can only be issued for active accounts.');
        }
        if ($user->member_id && $user->member->status !== 'active') {
            throw new DomainRuleException('The member is not active. Reactivate the member first.');
        }

        $raw = Tokens::generate(32);
        $record = DB::transaction(function () use ($user, $purpose, $via, $issuedBy, $raw) {
            AccountToken::where('user_id', $user->id)->where('purpose', $purpose)
                ->whereNull('consumed_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

            $expires = $purpose === 'activation'
                ? now()->addDays((int) config('boba.accounts.activation_token_days'))
                : now()->addMinutes((int) config('boba.accounts.reset_token_minutes'));

            return AccountToken::create([
                'user_id' => $user->id, 'purpose' => $purpose, 'token_hash' => Tokens::hash($raw),
                'expires_at' => $expires, 'issued_via' => $via, 'issued_by' => $issuedBy,
            ]);
        });

        Audit::record("account.{$purpose}_issued", ucfirst(str_replace('_', ' ', $purpose))." link issued for {$user->name}", $user, ['via' => $via, 'expires_at' => $record->expires_at->toIso8601String()], $issuedBy);

        $route = $purpose === 'activation' ? 'account.activate' : 'password.reset';

        return ['token' => $raw, 'url' => route($route, ['token' => $raw]), 'record' => $record];
    }

    /**
     * Prepare the delivery record for an issued link. The stored body never
     * contains the link; if SMTP is enabled the link is sent but not stored.
     */
    public function prepareLinkMessage(User $user, string $purpose, string $url, AccountToken $token): OutboundMessage
    {
        $label = $purpose === 'activation' ? 'activate your member account' : 'reset your password';
        $expiry = $token->expires_at->copy()->timezone(config('boba.display_timezone'))->format('j M Y, g:i a');
        $template = "Dear {$user->name},\n\nUse the private one-time link provided with this message to {$label}. The link expires on {$expiry} and can be used once.\n\nIf you did not expect this message, contact the secretariat.\n\nẸgbẹ́ Bobagunwa Okunrin Akile Ijebu";

        $message = $this->messages->prepare(
            $purpose === 'activation' ? 'activation_link' : 'password_reset_link',
            $user->name, $user->email, $user->member?->phone,
            $purpose === 'activation' ? 'Activate your Bobagunwa member account' : 'Reset your Bobagunwa password',
            $template."\n\n[One-time link withheld from records]", $user
        );
        $this->messages->attemptEmail($message, str_replace('provided with this message', 'below', $template)."\n\n{$url}\n");

        return $message->fresh();
    }

    public function findUsableToken(string $raw, string $purpose): ?AccountToken
    {
        $token = AccountToken::with('user.member')->where('token_hash', Tokens::hash($raw))->where('purpose', $purpose)->first();

        return $token && $token->isUsable() ? $token : null;
    }

    public function activate(string $raw, string $password): User
    {
        $failure = null;
        try {
            return $this->activateWithin($raw, $password, $failure);
        } catch (DomainRuleException $e) {
            if ($failure) {
                Audit::security('activation.rejected', $failure[0], ['reason' => $failure[1]]);
            }
            throw $e;
        }
    }

    private function activateWithin(string $raw, string $password, ?array &$failure): User
    {
        return DB::transaction(function () use ($raw, $password, &$failure) {
            $token = AccountToken::where('token_hash', Tokens::hash($raw))->where('purpose', 'activation')->lockForUpdate()->first();
            if (! $token || ! $token->isUsable()) {
                $failure = [$token?->user_id, $token ? 'expired_or_used' : 'unknown'];
                throw new DomainRuleException('This activation link is invalid, has expired or has already been used. Contact the secretariat for a new link.');
            }
            $user = User::with('member')->lockForUpdate()->findOrFail($token->user_id);
            if ($user->status !== 'pending_activation' || ($user->member && $user->member->status !== 'active')) {
                $failure = [$user->id, 'account_state'];
                throw new DomainRuleException('This account cannot be activated. Contact the secretariat.');
            }
            $this->assertPasswordPolicy($password, $user);
            $user->forceFill(['password' => $password, 'status' => 'active', 'activated_at' => now(), 'password_changed_at' => now(), 'remember_token' => Str::random(60)])->save();
            $token->update(['consumed_at' => now()]);
            Audit::security('account.activated', $user->id);
            Audit::record('account.activated', "Account activated for {$user->name}", $user, [], $user->id);

            return $user;
        });
    }

    public function resetPassword(string $raw, string $password): User
    {
        $failure = null;
        try {
            return $this->resetWithin($raw, $password, $failure);
        } catch (DomainRuleException $e) {
            if ($failure) {
                Audit::security('password_reset.rejected', $failure[0], ['reason' => $failure[1]]);
            }
            throw $e;
        }
    }

    private function resetWithin(string $raw, string $password, ?array &$failure): User
    {
        return DB::transaction(function () use ($raw, $password, &$failure) {
            $token = AccountToken::where('token_hash', Tokens::hash($raw))->where('purpose', 'password_reset')->lockForUpdate()->first();
            if (! $token || ! $token->isUsable()) {
                $failure = [$token?->user_id, $token ? 'expired_or_used' : 'unknown'];
                throw new DomainRuleException('This reset link is invalid, has expired or has already been used. Request a new one.');
            }
            $user = User::with('member')->lockForUpdate()->findOrFail($token->user_id);
            if (! $user->isActive()) {
                $failure = [$user->id, 'account_state'];
                throw new DomainRuleException('This account is not active. Contact the secretariat.');
            }
            $this->assertPasswordPolicy($password, $user);
            $user->forceFill(['password' => $password, 'password_changed_at' => now(), 'remember_token' => Str::random(60)])->save();
            $token->update(['consumed_at' => now()]);
            $this->endAllSessions($user);
            Audit::security('password.reset', $user->id);

            return $user;
        });
    }

    public function changePassword(User $user, string $current, string $new, ?string $keepSessionId): void
    {
        if (! Hash::check($current, (string) $user->password)) {
            Audit::security('password.change_rejected', $user->id);
            throw new DomainRuleException('Your current password is not correct.');
        }
        $this->assertPasswordPolicy($new, $user);
        $user->forceFill(['password' => $new, 'password_changed_at' => now(), 'remember_token' => Str::random(60)])->save();
        $this->endAllSessions($user, $keepSessionId);
        Audit::security('password.changed', $user->id);
    }

    public function endAllSessions(User $user, ?string $exceptSessionId = null): int
    {
        return DB::table('sessions')->where('user_id', $user->id)
            ->when($exceptSessionId, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->delete();
    }

    public function assertPasswordPolicy(string $password, User $user): void
    {
        $min = (int) config('boba.accounts.password_min_length');
        if (mb_strlen($password) < $min || mb_strlen($password) > 128) {
            throw new DomainRuleException("Choose a password of {$min} to 128 characters.");
        }
        if (in_array(hash('sha256', $password), config('boba.accounts.denied_password_sha256', []), true)) {
            throw new DomainRuleException('This password has been shown publicly and cannot be used. Choose another.');
        }
        $lower = mb_strtolower($password);
        foreach (array_filter([$user->email, $user->member?->membership_number, 'bobagunwa', 'password']) as $weak) {
            if (str_contains($lower, mb_strtolower((string) $weak))) {
                throw new DomainRuleException('Choose a password that does not contain your email, membership number or common words.');
            }
        }
        if (count(array_unique(mb_str_split($password))) < 5) {
            throw new DomainRuleException('Choose a less predictable password.');
        }
    }
}
