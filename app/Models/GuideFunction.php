<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuideFunction extends Model
{
    protected $fillable = [
        'category_id',
        'name',
    ];

    /**
     * Get the category that owns the function.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(GuideCategory::class, 'category_id');
    }

    /**
     * Get the contents for the function.
     */
    public function contents(): HasMany
    {
        return $this->hasMany(GuideContent::class, 'function_id');
    }
}
