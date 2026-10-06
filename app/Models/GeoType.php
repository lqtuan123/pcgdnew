<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GeoType extends Model
{
    use HasFactory;

    protected $fillable = [
        'level','key','variant','title_vi','short_vi','use_prefix','is_default',
    ];

    protected $casts = [
        'use_prefix' => 'bool',
        'is_default' => 'bool',
    ];

    public function units() {
        return $this->hasMany(GeoUnit::class, 'geo_type_id');
    }
}
