<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    protected $table = 'backup_runs';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
