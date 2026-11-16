<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enquiry extends Model
{
    protected $table = 'enquiries';

    protected $guarded = ['id'];

    protected $casts = ['resolved_at' => 'datetime', 'consent_at' => 'datetime'];

    public function notes(): HasMany
    {
        return $this->hasMany(EnquiryNote::class)->orderBy('id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
