<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObligationAdjustment extends Model
{
    protected $table = 'obligation_adjustments';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected $casts = ['effective_on' => 'date', 'created_at' => 'datetime'];

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(MemberObligation::class, 'member_obligation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
