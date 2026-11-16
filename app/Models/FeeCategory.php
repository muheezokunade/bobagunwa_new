<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeeCategory extends Model
{
    protected $table = 'fee_categories';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
