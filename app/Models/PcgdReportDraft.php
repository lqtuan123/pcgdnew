<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcgdReportDraft extends Model
{
    protected $fillable = [
        'user_id',
        'report_type',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get the user that owns the draft.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
