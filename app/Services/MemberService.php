<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Member;
use App\Models\User;
use App\Support\Audit;
use App\Support\Normalize;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Register maintenance with optimistic locking and field-level audit history.
 */
class MemberService
{
    public function __construct(private AccountService $accounts)
    {
    }

    /** @param array<string, mixed> $changes */
    public function correct(Member $member, array $changes, int $expectedVersion, User $actor, string $reason, bool $selfService = false): Member
    {
        $allowed = $selfService ? Member::SELF_SERVICE_FIELDS : Member::CORRECTABLE_FIELDS;
        $changes = array_intersect_key($changes, array_flip($allowed));

        try {
            return DB::transaction(function () use ($member, $changes, $expectedVersion, $actor, $reason, $selfService) {
                $locked = Member::lockForUpdate()->findOrFail($member->id);
                if ($locked->version !== $expectedVersion) {
                    throw new DomainRuleException('This record was changed by someone else while you were editing. Reload and try again.');
                }
                $diff = [];
                foreach ($changes as $field => $value) {
                    $value = is_string($value) ? Normalize::text($value) : $value;
                    $old = $locked->{$field} instanceof \DateTimeInterface ? $locked->{$field}->format('Y-m-d') : $locked->{$field};
                    if ((string) $old !== (string) $value) {
                        $diff[$field] = ['from' => $old, 'to' => $value];
                        $locked->{$field} = $value;
                    }
                }
                if (! $diff) {
                    return $locked;
                }
                if (array_key_exists('full_name', $diff)) {
                    $locked->name_normalized = Normalize::name($locked->full_name);
                }
                if (array_key_exists('email', $diff)) {
                    $locked->email_normalized = Normalize::email($locked->email);
                    if ($locked->user && $locked->user->email !== $locked->email_normalized && $locked->email_normalized) {
                        $locked->user->update(['email' => $locked->email_normalized]);
                    }
                }
                if (array_key_exists('phone', $diff)) {
                    $locked->phone_normalized = Normalize::phone($locked->phone);
                }
                $locked->version++;
                $locked->save();
                Audit::record($selfService ? 'member.profile_updated' : 'member.corrected', ($selfService ? 'Member updated own profile' : "Record corrected for {$locked->membership_number}"), $locked, ['changes' => $diff, 'reason' => $reason]);

                return $locked;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw new DomainRuleException('Another member record already uses that email address or phone number.');
        }
    }

    public function changeStatus(Member $member, string $status, string $reason, User $actor): Member
    {
        if (! in_array($status, ['active', 'suspended', 'inactive'], true)) {
            throw new DomainRuleException('Unknown member status.');
        }
        if (trim($reason) === '') {
            throw new DomainRuleException('Record the reason for this status change.');
        }

        return DB::transaction(function () use ($member, $status, $reason) {
            $locked = Member::lockForUpdate()->findOrFail($member->id);
            if ($locked->status === $status) {
                throw new DomainRuleException('The member already has this status.');
            }
            $from = $locked->status;
            $locked->update(['status' => $status, 'status_reason' => $reason, 'version' => $locked->version + 1]);
            if ($status !== 'active' && $locked->user) {
                $this->accounts->endAllSessions($locked->user);
                Audit::security('member.sessions_ended', $locked->user->id, ['reason' => "member {$status}"]);
            }
            Audit::record('member.status_changed', "{$locked->membership_number} changed from {$from} to {$status}", $locked, ['from' => $from, 'to' => $status, 'reason' => $reason]);

            return $locked;
        });
    }

    /** @return array{token: string, url: string, message_id: int} */
    public function issueAccess(Member $member, User $officer): array
    {
        if (! $officer->hasPermission('accounts.issue')) {
            throw new DomainRuleException('You do not have permission to issue account links.');
        }
        $user = DB::transaction(fn () => $this->accounts->provisionMemberAccount($member));
        $purpose = $user->status === 'pending_activation' ? 'activation' : 'password_reset';
        $link = $this->accounts->issue($user, $purpose, 'admin', $officer->id);
        $message = $this->accounts->prepareLinkMessage($user, $purpose, $link['url'], $link['record']);

        return ['token' => $link['token'], 'url' => $link['url'], 'message_id' => $message->id, 'purpose' => $purpose];
    }
}
