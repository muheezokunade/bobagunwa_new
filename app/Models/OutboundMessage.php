<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboundMessage extends Model
{
    protected $table = 'outbound_messages';

    protected $guarded = ['id'];

    protected $casts = ['attempted_at' => 'datetime', 'attested_at' => 'datetime'];

    public function attester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attested_by');
    }
}
