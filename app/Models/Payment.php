<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $table = 'payments';

    protected $guarded = ['id'];

    protected $casts = ['received_on' => 'date', 'verified_at' => 'datetime', 'rejected_at' => 'datetime', 'requires_distinct_verifier' => 'boolean'];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(FinancialReversal::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(PrivateFile::class, 'evidence_file_id');
    }
}
