<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A past Awujale or other historical figure shown under History and heritage. */
class HistoricalFigure extends Model
{
    protected $table = 'historical_figures';

    protected $guarded = ['id'];

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }
}
