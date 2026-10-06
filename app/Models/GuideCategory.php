<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuideCategory extends Model
{
    protected $fillable = [
        'role_type',
        'name',
    ];

    protected $casts = [
        'role_type' => 'string',
    ];

    /**
     * Get the functions for the category.
     */
    public function functions(): HasMany
    {
        return $this->hasMany(GuideFunction::class, 'category_id');
    }
}
