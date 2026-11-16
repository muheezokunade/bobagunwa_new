<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrityCheckRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['missing_triggers' => 'array', 'exception_counts' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
