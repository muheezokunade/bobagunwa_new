<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    protected $table = 'import_rows';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['raw' => 'array', 'normalized' => 'array', 'messages' => 'array'];
}
