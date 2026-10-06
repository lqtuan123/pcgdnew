<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatisticalFormula extends Model
{
    protected $fillable = [
        'report_detail_id',
        'criteria',
        'formula',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'report_detail_id' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relationship với ReportDetail
     */
    public function reportDetail(): BelongsTo
    {
        return $this->belongsTo(ReportDetail::class);
    }

    /**
     * Scope để lấy các công thức đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope để sắp xếp theo thứ tự
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope để lấy công thức theo báo cáo
     */
    public function scopeByReport($query, $reportDetailId)
    {
        return $query->where('report_detail_id', $reportDetailId);
    }
}
