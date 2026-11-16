<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $table = 'events';

    protected $guarded = ['id'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'rsvp_closes_at' => 'datetime', 'published_at' => 'datetime', 'rsvp_enabled' => 'boolean'];

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function rsvpOpen(): bool
    {
        return $this->rsvp_enabled && $this->status === 'published'
            && ($this->rsvp_closes_at === null || $this->rsvp_closes_at->isFuture())
            && $this->starts_at->isFuture();
    }
}
