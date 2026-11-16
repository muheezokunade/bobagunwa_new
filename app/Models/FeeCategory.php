<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FeeCategory extends Model
{
    protected $table = 'fee_categories';

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
