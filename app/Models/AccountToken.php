<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountToken extends Model
{
    protected $table = 'account_tokens';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime', 'revoked_at' => 'datetime', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
