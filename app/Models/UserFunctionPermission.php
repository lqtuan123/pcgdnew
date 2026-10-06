<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFunctionPermission extends Model
{
    use HasFactory;

    protected $table = 'user_function_permissions';

    protected $fillable = [
        'user_id',
        'function_id',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
    ];

    /**
     * Quan hệ với User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Quan hệ với SystemFunction
     */
    public function function(): BelongsTo
    {
        return $this->belongsTo(SystemFunction::class, 'function_id');
    }

    /**
     * Kiểm tra có quyền với action cụ thể không
     */
    public function hasPermission(string $action): bool
    {
        return match($action) {
            'view' => $this->can_view,
            'create' => $this->can_create,
            'edit' => $this->can_edit,
            'delete' => $this->can_delete,
            default => false,
        };
    }

    /**
     * Lấy tất cả quyền dưới dạng array
     */
    public function getAllPermissions(): array
    {
        return [
            'view' => $this->can_view,
            'create' => $this->can_create,
            'edit' => $this->can_edit,
            'delete' => $this->can_delete,
        ];
    }

    /**
     * Cập nhật quyền
     */
    public function updatePermissions(array $permissions): bool
    {
        return $this->update([
            'can_view' => $permissions['view'] ?? $this->can_view,
            'can_create' => $permissions['create'] ?? $this->can_create,
            'can_edit' => $permissions['edit'] ?? $this->can_edit,
            'can_delete' => $permissions['delete'] ?? $this->can_delete,
        ]);
    }

    /**
     * Scope để lấy quyền của user cụ thể
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope để lấy quyền với chức năng cụ thể
     */
    public function scopeForFunction($query, int $functionId)
    {
        return $query->where('function_id', $functionId);
    }
}
