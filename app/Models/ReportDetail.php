<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportDetail extends Model
{
    protected $fillable = [
        'report_group_id',
        'name',
        'code',
        'description',
        'value',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'report_group_id' => 'integer',
    ];

    /**
     * Relationship với ReportGroup
     */
    public function reportGroup(): BelongsTo
    {
        return $this->belongsTo(ReportGroup::class);
    }

    /**
     * Relationship với ReportConfig (1-1)
     */
    public function reportConfig(): HasOne
    {
        return $this->hasOne(ReportConfig::class);
    }

    /**
     * Relationship với StatisticalFormula (1-n)
     */
    public function statisticalFormulas(): HasMany
    {
        return $this->hasMany(StatisticalFormula::class);
    }

    /**
     * Scope để lấy các báo cáo chi tiết đang hoạt động
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
     * Scope để lấy báo cáo theo nhóm
     */
    public function scopeByGroup($query, $groupId)
    {
        return $query->where('report_group_id', $groupId);
    }
}
