<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Logo extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'original_path',
        'is_active',
        'name',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sizes(): HasMany
    {
        return $this->hasMany(LogoSize::class);
    }

    public function getActiveSizeAttribute()
    {
        return $this->sizes()->where('is_active', true)->first();
    }

    public function getUrlAttribute()
    {
        $activeSize = $this->activeSize;
        return $activeSize ? asset('storage/' . $activeSize->path) : null;
    }
}
