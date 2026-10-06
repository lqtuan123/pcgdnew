<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogoSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'logo_id',
        'width',
        'height',
        'path',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Logo::class);
    }

    public function getSizeNameAttribute()
    {
        return "{$this->width}x{$this->height}";
    }

    public function getUrlAttribute()
    {
        return asset('storage/' . $this->path);
    }
}
