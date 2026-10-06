<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportGroup extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Relationship với ReportDetail
     */
    public function reportDetails(): HasMany
    {
        return $this->hasMany(ReportDetail::class)->orderBy('sort_order');
    }

    /**
     * Scope để lấy các nhóm báo cáo đang hoạt động
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
}
