<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ImportRow extends Model
{
    protected $table = 'import_rows';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['raw' => 'array', 'normalized' => 'array', 'messages' => 'array'];
}
