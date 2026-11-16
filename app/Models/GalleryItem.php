<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryItem extends Model
{
    protected $table = 'gallery_items';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
