<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisabilityCategory extends Model
{
    protected $fillable = [
        'code','name','short_name','sort_order','is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function personDisabilities()
    {
        return $this->hasMany(PersonDisability::class);
    }
}
