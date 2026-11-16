<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MembershipNumber extends Model
{
    protected $table = 'membership_numbers';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['issued_at' => 'datetime'];
}
