<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\AutoGrantPermissions;

class SystemFunction extends Model
{
    use HasFactory, AutoGrantPermissions;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Quan hệ với user roles thông qua role_function_permissions
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(UserRole::class, 'role_function_permissions', 'function_id', 'role_id')
            ->withPivot(['can_view', 'can_create', 'can_edit', 'can_delete'])
            ->withTimestamps();
    }

    /**
     * Quan hệ với users thông qua user_function_permissions
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_function_permissions', 'function_id', 'user_id')
            ->withPivot(['can_view', 'can_create', 'can_edit', 'can_delete'])
            ->withTimestamps();
    }

    /**
     * Scope để lấy chức năng theo category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope để lấy chức năng active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope để lấy chức năng theo code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Lấy danh sách chức năng theo category
     */
    public static function getByCategory(string $category): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()->byCategory($category)->get();
    }

    /**
     * Lấy tất cả categories
     */
    public static function getAllCategories(): array
    {
        return static::active()
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Kiểm tra chức năng có active không
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Lấy tên hiển thị của chức năng
     */
    public function getDisplayName(): string
    {
        return $this->name;
    }

    /**
     * Lấy mô tả chi tiết
     */
    public function getDescription(): string
    {
        return $this->description ?: "Chức năng {$this->name}";
    }
}
