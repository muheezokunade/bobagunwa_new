<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivateFile extends Model
{
    protected $table = 'private_files';

    protected $guarded = ['id'];

    public const UPDATED_AT = null;
}
