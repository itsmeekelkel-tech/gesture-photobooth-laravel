<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    protected $fillable = ['path'];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    protected $appends = ['url'];
}
