<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\ApplicationAttachment;
use App\Models\ApplicationEvent;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\PrivateFile;
use App\Models\User;
use App\Support\Audit;
use App\Support\Normalize;
use App\Support\Settings;
use App\Support\Tokens;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Public membership applications: submission, eligibility screening, private
 * status tracking and the authorised review workflow.
 *
 * Policy 2026-10-11-v1 (docs/11-membership-procedures.md): the inclusive
 * birth-date range is 1980-01-01 to 1982-12-31. A date outside the range is
 * flagged for human review — never automatically approved or discarded.
 * Submission never creates a member or an account.
 */
class ApplicationService
{
    public function __construct(
        private MembershipNumberService $numbers,
        private AccountService $accounts,
        private MessageService $messages,
    ) {}

    /** @return array{status: string, flags: list<array{code: string, message: string}>} */
    public function screen(CarbonImmutable $dateOfBirth): array
    {
        $from = CarbonImmutable::parse(config('boba.membership.birth_date_from'));
        $to = CarbonImmutable::parse(config('boba.membership.birth_date_to'));
        $flags = [];
        if ($dateOfBirth->lt($from) || $dateOfBirth->gt($to)) {
            $flags[] = ['code' => 'birth_date_outside_range', 'message' => 'Date of birth is outside the confirmed range of 1 January 1980 to 31 December 1982. Requires human consideration.'];
        }

        return ['status' => $flags ? 'flagged' : 'eligible', 'flags' => $flags];
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     * @return array{0: MembershipApplication, 1: string} application and raw status token
     */
    /**
     * @param  array<string, list<PrivateFile>>  $attachments  kind => stored private files (already validated)
     */
    public function submit(array $data, array $attachments = []): array
    {
        if (! Settings::bool('membership.applications_open')) {
            throw new DomainRuleException('Membership applications are not being accepted at the moment. Please contact the secretariat.');
        }
        $email = Normalize::email($data['email']);
        $phone = Normalize::phone($data['phone']);
        $dob = CarbonImmutable::parse($data['date_of_birth']);
        $screening = $this->screen($dob);

        if (MembershipApplication::whereIn('status', MembershipApplication::OPEN_STATUSES)
            ->where(fn ($q) => $q->where('email_normalized', $email)->orWhere('phone_normalized', $phone))->exists()) {
            throw new DomainRuleException('An application with these contact details is already being processed. Use your reference to check its progress, or contact the secretariat.');
        }
        if (Member::where('email_normalized', $email)->orWhere('phone_normalized', $phone)->exists()) {
            // Not disclosed to the applicant; the reviewer sees the flag.
            $screening['flags'][] = ['code' => 'possible_existing_member', 'message' => 'Email or phone matches an existing member record.'];
            $screening['status'] = 'flagged';
        }

        $rawToken = Tokens::generate(24);
        try {
            $application = DB::transaction(function () use ($data, $email, $phone, $screening, $rawToken, $attachments) {
                $application = MembershipApplication::create([
                    'reference' => $this->uniqueReference(),
                    'status_token_hash' => Tokens::hash($rawToken),
                    'status_token_expires_at' => now()->addDays((int) config('boba.membership.status_token_days')),
                    'first_name' => Normalize::text($data['first_name']),
                    'last_name' => Normalize::text($data['last_name']),
                    'other_names' => Normalize::text($data['other_names'] ?? null),
                    'email' => trim($data['email']),
                    'email_normalized' => $email,
                    'phone' => trim($data['phone']),
                    'phone_normalized' => $phone,
                    'date_of_birth' => $data['date_of_birth'],
                    'ijebu_town' => Normalize::text($data['ijebu_town']),
                    'city' => Normalize::text($data['city']),
                    'country' => Normalize::text($data['country'] ?? null),
                    'address' => Normalize::text($data['address'] ?? null),
                    'marital_status' => $data['marital_status'] ?? null,
                    'occupation' => Normalize::text($data['occupation']),
                    'profile_summary' => isset($data['profile_summary']) ? (trim($data['profile_summary']) ?: null) : null,
                    'reason' => isset($data['reason']) ? (trim($data['reason']) ?: null) : null,
                    'eligibility_status' => $screening['status'],
                    'eligibility_flags' => $screening['flags'],
                    'screening_version' => config('boba.membership.screening_version'),
                    'privacy_notice_version' => config('boba.privacy_notice_version'),
                    'declaration_accepted_at' => now(),
                    'privacy_accepted_at' => now(),
                    'status' => 'submitted',
                    'submitted_ip_hash' => Audit::ipHash(),
                ]);
                foreach ($attachments as $kind => $files) {
                    foreach ($files as $file) {
                        ApplicationAttachment::create(['membership_application_id' => $application->id, 'private_file_id' => $file->id, 'kind' => $kind]);
                    }
                }
                ApplicationEvent::create(['membership_application_id' => $application->id, 'type' => 'submitted', 'to_status' => 'submitted', 'applicant_message' => 'Your application has been received and will be reviewed by the association.']);
                Audit::record('application.submitted', "Membership application {$application->reference} received", $application, ['eligibility' => $screening['status']]);

                return $application;
            });
        } catch (UniqueConstraintViolationException $e) {
            if (str_contains($e->getMessage(), 'applications_active_')) {
                throw new DomainRuleException('An application with these contact details is already being processed. Use your reference to check its progress, or contact the secretariat.');
            }
            throw $e;
        }

        return [$application, $rawToken];
    }

    /** Constant-response lookup by reference plus secret token. */
    public function findForApplicant(string $reference, string $token): ?MembershipApplication
    {
        $application = MembershipApplication::where('reference', strtoupper(trim($reference)))->first();
        $valid = $application
            && hash_equals($application->status_token_hash, Tokens::hash(trim($token)))
            && $application->status_token_expires_at->isFuture();
        if (! $valid) {
            Audit::security('application_status.lookup_failed', null, ['reference_format_ok' => (bool) preg_match('/^BOB-APP-\d{4}-[A-Z0-9]{6}$/', strtoupper(trim($reference)))]);
        }

        return $valid ? $application : null;
    }

    public function applicantRespond(MembershipApplication $application, string $message): void
    {
        DB::transaction(function () use ($application, $message) {
            $locked = MembershipApplication::lockForUpdate()->findOrFail($application->id);
            if ($locked->status !== 'changes_requested') {
                throw new DomainRuleException('This application is not waiting for more information.');
            }
            ApplicationEvent::create(['membership_application_id' => $locked->id, 'type' => 'applicant_response', 'applicant_message' => trim($message)]);
            ApplicationEvent::create(['membership_application_id' => $locked->id, 'type' => 'status_changed', 'from_status' => 'changes_requested', 'to_status' => 'under_review', 'applicant_message' => 'Thank you. Your reply has been passed to the reviewing officers.']);
            $locked->update(['status' => 'under_review', 'version' => $locked->version + 1]);
            $this->messages->supersede('application_information_request', $locked, 'the applicant has replied');
            Audit::record('application.applicant_responded', "Applicant responded on {$locked->reference}", $locked);
        });
    }

    /**
     * Review transitions other than approval.
     */
    public function transition(MembershipApplication $application, string $to, User $officer, string $privateNote, ?string $applicantMessage): MembershipApplication
    {
        if ($to === 'approved') {
            return $this->approve($application, $officer, $privateNote, $applicantMessage)[0]->application;
        }
        $this->authorise($officer);
        if (trim($privateNote) === '') {
            throw new DomainRuleException('Record a review note for this decision.');
        }
        if (in_array($to, ['changes_requested', 'rejected'], true) && trim((string) $applicantMessage) === '') {
            throw new DomainRuleException('Write the message the applicant will see.');
        }

        return DB::transaction(function () use ($application, $to, $officer, $privateNote, $applicantMessage) {
            $locked = MembershipApplication::lockForUpdate()->findOrFail($application->id);
            $this->assertTransition($locked, $to);
            if ($to === 'rejected') {
                $this->assertFinalDecision($locked, $officer, 'reject');
            }
            ApplicationEvent::create([
                'membership_application_id' => $locked->id, 'type' => 'status_changed', 'from_status' => $locked->status, 'to_status' => $to,
                'actor_user_id' => $officer->id, 'private_note' => trim($privateNote), 'applicant_message' => $applicantMessage ? trim($applicantMessage) : null,
            ]);
            $updates = ['status' => $to, 'version' => $locked->version + 1];
            if ($to === 'changes_requested') {
                // New information means a fresh review: any earlier recommendation no longer stands.
                $updates += ['recommendation' => null, 'recommended_by' => null, 'recommended_at' => null];
            }
            if ($to === 'rejected') {
                $updates += ['decided_by' => $officer->id, 'decided_at' => now()];
            }
            $from = $locked->status;
            $locked->update($updates);
            if ($from === 'changes_requested' && $to !== 'changes_requested') {
                $this->messages->supersede('application_information_request', $locked, "the application moved on to {$to}");
            }
            if (in_array($to, ['changes_requested', 'rejected'], true)) {
                $this->messages->prepare(
                    $to === 'rejected' ? 'application_decision' : 'application_information_request',
                    $locked->fullName(), $locked->email, $locked->phone,
                    $to === 'rejected' ? "Your Bobagunwa membership application {$locked->reference}" : "Information needed for application {$locked->reference}",
                    "Dear {$locked->first_name},\n\n".trim((string) $applicantMessage)."\n\nYou can follow your application at ".route('membership.status')." using your reference {$locked->reference} and the private tracking code you received when you applied.\n\nẸgbẹ́ Bobagunwa Okunrin Akile Ijebu",
                    $locked, $officer->id
                );
            }
            Audit::record("application.{$to}", "Application {$locked->reference} moved from {$from} to {$to}", $locked, ['note' => $privateNote]);

            return $locked;
        });
    }

    /**
     * Approve an application: creates exactly one member, one permanent
     * membership number and one pending account with a single-use activation
     * link. A second approval is refused.
     *
     * @return array{0: Member, 1: array{token: string, url: string}|null}
     */
    public function approve(MembershipApplication $application, User $officer, string $privateNote, ?string $applicantMessage): array
    {
        $this->authorise($officer);
        if (trim($privateNote) === '') {
            throw new DomainRuleException('Record a review note for this decision.');
        }

        [$member, $user] = DB::transaction(function () use ($application, $officer, $privateNote, $applicantMessage) {
            $locked = MembershipApplication::lockForUpdate()->findOrFail($application->id);
            if ($locked->status === 'approved' || $locked->member_id) {
                throw new DomainRuleException('This application has already been approved.');
            }
            $this->assertTransition($locked, 'approved');
            $this->assertFinalDecision($locked, $officer, 'approve');
            if (Member::where('email_normalized', $locked->email_normalized)->orWhere('phone_normalized', $locked->phone_normalized)->exists()) {
                throw new DomainRuleException('A member with the same email address or phone number already exists. Resolve the possible duplicate before approving.');
            }

            $number = $this->numbers->issue(null, $officer->id);
            $member = Member::create([
                'membership_number' => $number,
                'full_name' => $locked->fullName(),
                'name_normalized' => Normalize::name($locked->fullName()),
                'email' => $locked->email,
                'email_normalized' => $locked->email_normalized,
                'phone' => $locked->phone,
                'phone_normalized' => $locked->phone_normalized,
                'date_of_birth' => $locked->date_of_birth,
                'ijebu_town' => $locked->ijebu_town,
                'city' => $locked->city,
                'country' => $locked->country,
                'address' => $locked->address,
                'marital_status' => $locked->marital_status,
                'occupation' => $locked->occupation,
                'profile_summary' => $locked->profile_summary,
                'status' => 'active',
                'origin' => 'application',
                'membership_application_id' => $locked->id,
                'joined_on' => now()->toDateString(),
                'created_by' => $officer->id,
            ]);
            $message = trim((string) $applicantMessage) ?: 'Your application has been approved. Welcome to the association.';
            ApplicationEvent::create([
                'membership_application_id' => $locked->id, 'type' => 'status_changed', 'from_status' => $locked->status, 'to_status' => 'approved',
                'actor_user_id' => $officer->id, 'private_note' => trim($privateNote), 'applicant_message' => $message,
            ]);
            $locked->update(['status' => 'approved', 'member_id' => $member->id, 'decided_by' => $officer->id, 'decided_at' => now(), 'version' => $locked->version + 1]);
            $this->messages->supersede('application_information_request', $locked, 'the application was approved');
            $user = $this->accounts->provisionMemberAccount($member);
            $this->messages->prepare('application_decision', $member->full_name, $member->email, $member->phone,
                "Your Bobagunwa membership application {$locked->reference}",
                "Dear {$locked->first_name},\n\n{$message}\n\nYour membership number is {$number}. You will receive a separate private link to activate your member account.\n\nẸgbẹ́ Bobagunwa Okunrin Akile Ijebu",
                $locked, $officer->id);
            Audit::record('application.approved', "Application {$locked->reference} approved; member {$number} created", $locked, ['member_id' => $member->id, 'note' => $privateNote]);

            return [$member, $user];
        });

        $link = $this->accounts->issue($user, 'activation', 'approval', $officer->id);
        $message = $this->accounts->prepareLinkMessage($user, 'activation', $link['url'], $link['record']);

        return [$member->fresh('application'), ['token' => $link['token'], 'url' => $link['url'], 'message_id' => $message->id]];
    }

    private function authorise(User $officer): void
    {
        if (! $officer->hasPermission('applications.review')) {
            throw new DomainRuleException('You do not have permission to review membership applications.');
        }
    }

    /**
     * Final decisions need applications.decide and, with two-step approval on,
     * a matching recommendation from a different officer.
     */
    private function assertFinalDecision(MembershipApplication $locked, User $officer, string $outcome): void
    {
        if (! $officer->hasPermission('applications.decide')) {
            throw new DomainRuleException('Only an officer with final-decision rights can approve or reject an application. Record a recommendation instead.');
        }
        if (! Settings::bool('membership.two_step_approval')) {
            return;
        }
        if ($locked->recommendation !== $outcome) {
            throw new DomainRuleException($outcome === 'approve'
                ? 'This application has not been recommended for approval yet. The General Secretary (or another reviewer) records the recommendation first.'
                : 'This application has not been recommended for rejection yet. The General Secretary (or another reviewer) records the recommendation first.');
        }
        if ((int) $locked->recommended_by === $officer->id) {
            throw new DomainRuleException('The final decision must be made by a different officer from the one who recommended it.');
        }
    }

    /** Step one: record a recommendation (approve or reject) for the final approver. */
    public function recommend(MembershipApplication $application, User $officer, string $outcome, string $privateNote): MembershipApplication
    {
        $this->authorise($officer);
        if (! in_array($outcome, ['approve', 'reject'], true)) {
            throw new DomainRuleException('Choose a recommendation.');
        }
        if (trim($privateNote) === '') {
            throw new DomainRuleException('Record a review note explaining the recommendation.');
        }

        return DB::transaction(function () use ($application, $officer, $outcome, $privateNote) {
            $locked = MembershipApplication::lockForUpdate()->findOrFail($application->id);
            if ($locked->status !== 'under_review') {
                throw new DomainRuleException('Only an application under review can be recommended.');
            }
            ApplicationEvent::create([
                'membership_application_id' => $locked->id, 'type' => 'recommended', 'actor_user_id' => $officer->id,
                'private_note' => 'Recommended '.($outcome === 'approve' ? 'approval' : 'rejection').': '.trim($privateNote),
            ]);
            $locked->update(['recommendation' => $outcome, 'recommended_by' => $officer->id, 'recommended_at' => now(), 'version' => $locked->version + 1]);
            Audit::record('application.recommended', "Application {$locked->reference} recommended for ".($outcome === 'approve' ? 'approval' : 'rejection'), $locked, ['note' => $privateNote]);

            return $locked;
        });
    }

    private function assertTransition(MembershipApplication $application, string $to): void
    {
        if (! in_array($to, MembershipApplication::TRANSITIONS[$application->status] ?? [], true)) {
            throw new DomainRuleException('This application cannot move from "'.MembershipApplication::statusLabel($application->status).'" to "'.MembershipApplication::statusLabel($to).'".');
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = Tokens::reference('BOB-APP');
        } while (MembershipApplication::where('reference', $reference)->exists());

        return $reference;
    }
}
