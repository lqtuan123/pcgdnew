<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Religion extends Model
{
    protected $fillable = [
        'code','name','slug','is_official','note',
    ];

    protected $casts = [
        'is_official' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if (blank($m->slug) && filled($m->name)) {
                $m->slug = Str::slug($m->name);
            }
        });
    }
}
