<?php

namespace App\Models;

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
        'under_review' => ['changes_requested', 'approved', 'rejected'],
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
    ];

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
        ][$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
