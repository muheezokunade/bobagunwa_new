<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipNumber extends Model
{
    protected $table = 'membership_numbers';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['issued_at' => 'datetime'];
}
