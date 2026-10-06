<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportConfig extends Model
{
    protected $fillable = [
        'report_detail_id',
        'status',
    ];

    protected $casts = [
        'report_detail_id' => 'integer',
    ];

    /**
     * Relationship với ReportDetail
     */
    public function reportDetail(): BelongsTo
    {
        return $this->belongsTo(ReportDetail::class);
    }

    /**
     * Scope để lấy các cấu hình bắt buộc
     */
    public function scopeRequired($query)
    {
        return $query->where('status', 'required');
    }

    /**
     * Scope để lấy các cấu hình không bắt buộc
     */
    public function scopeOptional($query)
    {
        return $query->where('status', 'optional');
    }

    /**
     * Scope để lấy các cấu hình không sử dụng
     */
    public function scopeDisabled($query)
    {
        return $query->where('status', 'disabled');
    }

    /**
     * Scope để lấy các cấu hình đang hoạt động (required hoặc optional)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['required', 'optional']);
    }
}
