<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideContent extends Model
{
    protected $fillable = [
        'function_id',
        'title',
        'content',
        'video_url',
    ];

    /**
     * Get the function that owns the content.
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(GuideFunction::class, 'function_id');
    }
}
