<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleFunctionPermission extends Model
{
    use HasFactory;

    protected $table = 'role_function_permissions';

    protected $fillable = [
        'role_id',
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
     * Quan hệ với UserRole
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'role_id');
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
}
