<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Media extends Model
{
    protected $table = 'media';

    protected $guarded = ['id'];

    public function url(): string
    {
        return asset('media/'.$this->path);
    }
}
