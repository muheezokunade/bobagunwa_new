<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Member;
use App\Models\User;
use App\Services\Finance\Ledger;
use App\Support\Audit;
use App\Support\Idempotency;
use App\Support\Normalize;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Register maintenance with optimistic locking and field-level audit history.
 */
class MemberService
{
    public function __construct(private AccountService $accounts) {}

    /** @param array<string, mixed> $changes */
    public function correct(Member $member, array $changes, int $expectedVersion, User $actor, string $reason, bool $selfService = false): Member
    {
        $allowed = $selfService ? Member::SELF_SERVICE_FIELDS : Member::CORRECTABLE_FIELDS;
        $changes = array_intersect_key($changes, array_flip($allowed));

        try {
            return DB::transaction(function () use ($member, $changes, $expectedVersion, $reason, $selfService) {
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

    /**
     * Add one existing member by hand. Email/phone duplicates are refused; a
     * same-name match is allowed only when the officer confirms the person is
     * different and records how (audited). No login account is created.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Member, 1: bool}
     */
    public function createManual(array $data, User $officer, bool $confirmDistinct, ?string $distinguishingNote, ?string $idempotencyKey): array
    {
        if (! $officer->hasPermission('members.manage')) {
            throw new DomainRuleException('You do not have permission to add members.');
        }
        $name = Normalize::text($data['full_name']);
        $email = Normalize::email($data['email'] ?? null);
        $phone = Normalize::phone($data['phone'] ?? null);
        if ($phone !== null && ! Normalize::isPlausiblePhone($phone)) {
            throw new DomainRuleException('Enter a valid phone number.');
        }
        $provided = isset($data['membership_number']) && trim((string) $data['membership_number']) !== '' ? strtoupper(trim($data['membership_number'])) : null;

        try {
            return Ledger::guard(fn () => Idempotency::run('member.create', $idempotencyKey, [$name, $email, $phone, $provided], function () use ($data, $officer, $confirmDistinct, $distinguishingNote, $name, $email, $phone, $provided) {
                if ($email && Member::where('email_normalized', $email)->exists()) {
                    throw new DomainRuleException('Another member already uses this email address.');
                }
                if ($phone && Member::where('phone_normalized', $phone)->exists()) {
                    throw new DomainRuleException('Another member already uses this phone number.');
                }
                $sameName = Member::where('name_normalized', Normalize::name($name))->pluck('membership_number');
                if ($sameName->isNotEmpty()) {
                    if (! $confirmDistinct || mb_strlen(trim((string) $distinguishingNote)) < 10) {
                        throw new DomainRuleException('A member with the same name already exists ('.$sameName->implode(', ').'). If this is a different person, tick the confirmation and explain how you verified they are different (at least 10 characters).');
                    }
                }
                $number = app(MembershipNumberService::class)->issue($provided, $officer->id);
                $notes = trim((string) ($data['admin_notes'] ?? ''));
                if ($sameName->isNotEmpty()) {
                    $notes = trim('Confirmed different person from '.$sameName->implode(', ').': '.trim((string) $distinguishingNote)."\n".$notes);
                }
                $member = Member::create([
                    'membership_number' => $number, 'full_name' => $name, 'name_normalized' => Normalize::name($name),
                    'preferred_name' => Normalize::text($data['preferred_name'] ?? null),
                    'email' => $email, 'email_normalized' => $email, 'phone' => $phone ? trim($data['phone']) : null, 'phone_normalized' => $phone,
                    'date_of_birth' => $data['date_of_birth'] ?? null, 'joined_on' => $data['joined_on'] ?? null,
                    'ijebu_town' => Normalize::text($data['ijebu_town'] ?? null), 'city' => Normalize::text($data['city'] ?? null),
                    'occupation' => Normalize::text($data['occupation'] ?? null), 'address' => Normalize::text($data['address'] ?? null),
                    'admin_notes' => $notes ?: null, 'status' => 'active', 'origin' => 'manual', 'created_by' => $officer->id,
                ]);
                Audit::record('member.created_manually', "Member {$number} added by hand", $member, ['same_name_confirmed' => $sameName->all(), 'reason' => $data['reason'] ?? null]);

                return $member;
            }, Member::class));
        } catch (UniqueConstraintViolationException $e) {
            throw new DomainRuleException('Another member already uses that email address or phone number.');
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
