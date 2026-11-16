<?php

namespace App\Services;

use App\Exceptions\DomainRuleException;
use App\Models\Enquiry;
use App\Models\EnquiryNote;
use App\Models\User;
use App\Support\Audit;
use App\Support\Normalize;
use App\Support\Tokens;

class EnquiryService
{
    public const STATUSES = ['new', 'in_progress', 'resolved', 'closed_spam'];

    /** @param array<string, string> $data */
    public function submit(array $data): Enquiry
    {
        do {
            $reference = Tokens::reference('ENQ');
        } while (Enquiry::where('reference', $reference)->exists());

        $enquiry = Enquiry::create([
            'reference' => $reference,
            'name' => Normalize::text($data['name']),
            'email' => trim($data['email']),
            'phone' => Normalize::text($data['phone'] ?? null),
            'topic' => $data['topic'],
            'subject' => Normalize::text($data['subject']),
            'message' => trim($data['message']),
            'status' => 'new',
            'consent_at' => now(),
            'ip_hash' => Audit::ipHash(),
        ]);
        Audit::record('enquiry.received', "Enquiry {$reference} received", $enquiry, ['topic' => $data['topic']]);

        return $enquiry;
    }

    public function update(Enquiry $enquiry, User $officer, string $status, ?int $assignee, ?string $note): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new DomainRuleException('Unknown enquiry status.');
        }
        if ($assignee && ! User::whereKey($assignee)->where('status', 'active')->whereHas('roles', fn ($q) => $q->where('is_staff', true))->exists()) {
            throw new DomainRuleException('Choose an active officer.');
        }
        $changes = [];
        if ($enquiry->status !== $status) {
            $changes['status'] = [$enquiry->status, $status];
        }
        if ($enquiry->assigned_to !== $assignee) {
            $changes['assigned_to'] = [$enquiry->assigned_to, $assignee];
        }
        $enquiry->update([
            'status' => $status, 'assigned_to' => $assignee,
            'resolved_at' => in_array($status, ['resolved', 'closed_spam'], true) ? ($enquiry->resolved_at ?? now()) : null,
        ]);
        if ($note && trim($note) !== '') {
            EnquiryNote::create(['enquiry_id' => $enquiry->id, 'user_id' => $officer->id, 'note' => trim($note)]);
        }
        Audit::record('enquiry.updated', "Enquiry {$enquiry->reference} updated", $enquiry, ['changes' => $changes, 'note_added' => (bool) $note]);
    }
}
