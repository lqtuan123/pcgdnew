<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunctionCategory extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Lấy danh sách chức năng thuộc danh mục này
     */
    public function functions(): HasMany
    {
        return $this->hasMany(SystemFunction::class, 'category', 'code');
    }

    /**
     * Scope: Chỉ lấy danh mục đang hoạt động
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Sắp xếp theo thứ tự
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
