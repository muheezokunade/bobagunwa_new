<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipApplication extends Model
{
    protected $guarded = ['id', 'active_email_key', 'active_phone_key'];

    protected $hidden = ['status_token_hash'];

    public const OPEN_STATUSES = ['submitted', 'under_review', 'changes_requested'];

    /** Allowed transitions; approval and rejection are final. */
    public const TRANSITIONS = [
        'submitted' => ['under_review'],
        'under_review' => ['changes_requested', 'recommend_approve', 'recommend_reject', 'approved', 'rejected'],
        'changes_requested' => ['under_review'],
        'approved' => [],
        'rejected' => [],
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'eligibility_flags' => 'array',
        'status_token_expires_at' => 'datetime',
        'declaration_accepted_at' => 'datetime',
        'privacy_accepted_at' => 'datetime',
        'decided_at' => 'datetime',
        'recommended_at' => 'datetime',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(ApplicationAttachment::class)->orderBy('id');
    }

    /**
     * The next steps this officer may take, given two-step approval:
     * reviewers recommend; a different officer with final-decision rights approves or rejects.
     *
     * @return list<string>
     */
    public function stepsFor(User $officer): array
    {
        $steps = self::TRANSITIONS[$this->status] ?? [];
        $twoStep = Settings::bool('membership.two_step_approval');
        $canDecide = $officer->hasPermission('applications.decide');

        return array_values(array_filter($steps, function ($step) use ($twoStep, $canDecide, $officer) {
            if (in_array($step, ['approved', 'rejected'], true)) {
                if (! $canDecide) {
                    return false;
                }

                return ! $twoStep || ($this->recommendation === ($step === 'approved' ? 'approve' : 'reject') && (int) $this->recommended_by !== $officer->id);
            }
            if (in_array($step, ['recommend_approve', 'recommend_reject'], true)) {
                return $twoStep;
            }

            return true;
        }));
    }

    public function recommender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommended_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class)->orderBy('id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->other_names, $this->last_name])));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public static function statusLabel(string $status): string
    {
        return [
            'submitted' => 'Received',
            'under_review' => 'Under review',
            'changes_requested' => 'More information requested',
            'approved' => 'Approved',
            'rejected' => 'Not approved',
            'recommend_approve' => 'Recommend approval (for the final approver)',
            'recommend_reject' => 'Recommend rejection (for the final approver)',
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
