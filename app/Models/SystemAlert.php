<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemAlert extends Model
{
    protected $guarded = ['id', 'open_fingerprint'];

    protected $casts = [
        'details' => 'array', 'first_detected_at' => 'datetime', 'last_detected_at' => 'datetime',
        'condition_cleared_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime',
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(AlertNotification::class)->orderBy('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(AlertReceipt::class)->orderBy('first_seen_at');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isUnresolved(): bool
    {
        return $this->status !== 'resolved';
    }
}
