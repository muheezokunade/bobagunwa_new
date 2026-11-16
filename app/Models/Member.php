<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An entry in the membership register. Never deleted. The visible membership
 * number is permanent and distinct from the internal database id.
 */
class Member extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_of_birth' => 'date',
        'joined_on' => 'date',
    ];

    /** Fields an officer with members.manage may correct. */
    public const CORRECTABLE_FIELDS = ['full_name', 'preferred_name', 'email', 'phone', 'date_of_birth', 'ijebu_town', 'city', 'occupation', 'address', 'joined_on', 'admin_notes'];

    /** Fields a member may update on their own profile. */
    public const SELF_SERVICE_FIELDS = ['preferred_name', 'phone', 'city', 'occupation', 'address'];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'membership_application_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(MemberObligation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function displayName(): string
    {
        return $this->preferred_name ?: $this->full_name;
    }

    /** @return list<string> Missing data an officer may need to complete. */
    public function incompleteFields(): array
    {
        return array_values(array_filter([
            $this->email ? null : 'email',
            $this->phone ? null : 'phone',
            $this->date_of_birth ? null : 'date of birth',
            $this->joined_on ? null : 'joined date',
        ]));
    }
}
