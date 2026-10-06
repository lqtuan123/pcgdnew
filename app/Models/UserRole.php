<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'is_system',
        'can_create_users',
        'can_assign_permissions',
        'can_manage_geo_permissions',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'can_create_users' => 'boolean',
        'can_assign_permissions' => 'boolean',
        'can_manage_geo_permissions' => 'boolean',
    ];

    /**
     * Quan hệ với users
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    /**
     * Quan hệ với system functions thông qua role_function_permissions
     */
    public function functions(): BelongsToMany
    {
        return $this->belongsToMany(SystemFunction::class, 'role_function_permissions', 'role_id', 'function_id')
            ->withPivot(['can_view', 'can_create', 'can_edit', 'can_delete'])
            ->withTimestamps();
    }

    /**
     * Kiểm tra role có thể tạo user không
     */
    public function canCreateUsers(): bool
    {
        return $this->can_create_users;
    }

    /**
     * Kiểm tra role có thể phân quyền không
     */
    public function canAssignPermissions(): bool
    {
        return $this->can_assign_permissions;
    }

    /**
     * Kiểm tra role có thể quản lý phân quyền địa giới không
     */
    public function canManageGeoPermissions(): bool
    {
        return $this->can_manage_geo_permissions;
    }


    /**
     * Kiểm tra role có quyền với chức năng cụ thể không
     */
    public function hasPermission(string $functionCode, string $action = 'view'): bool
    {
        $function = SystemFunction::where('code', $functionCode)->first();
        if (!$function) return false;

        $permission = $this->functions()
            ->where('system_functions.id', $function->id)
            ->first();

        if (!$permission) return false;

        return match($action) {
            'view' => $permission->pivot->can_view,
            'create' => $permission->pivot->can_create,
            'edit' => $permission->pivot->can_edit,
            'delete' => $permission->pivot->can_delete,
            default => false,
        };
    }

    /**
     * Lấy tất cả quyền của role
     */
    public function getAllPermissions(): array
    {
        $permissions = [];
        
        foreach ($this->functions as $function) {
            $permissions[$function->code] = [
                'view' => $function->pivot->can_view,
                'create' => $function->pivot->can_create,
                'edit' => $function->pivot->can_edit,
                'delete' => $function->pivot->can_delete,
            ];
        }

        return $permissions;
    }


    /**
     * Scope để lấy role có thể tạo user
     */
    public function scopeCanCreateUsers($query)
    {
        return $query->where('can_create_users', true);
    }

    /**
     * Scope để lấy role có thể phân quyền
     */
    public function scopeCanAssignPermissions($query)
    {
        return $query->where('can_assign_permissions', true);
    }

    /**
     * Scope để lấy roles có thể chọn (loại bỏ superadmin)
     */
    public function scopeSelectable($query)
    {
        return $query->where('name', '!=', 'superadmin');
    }

    /**
     * Lấy danh sách roles có thể chọn
     */
    public static function getSelectableRoles()
    {
        return self::selectable()->orderBy('name')->get();
    }

    /**
     * Kiểm tra role có phải là system role không
     */
    public function isSystemRole(): bool
    {
        return $this->is_system;
    }

    /**
     * Kiểm tra role có phải là superadmin không
     */
    public function isSuperadmin(): bool
    {
        return $this->name === 'superadmin' && $this->is_system;
    }

    /**
     * Scope để lấy roles có thể quản lý (không phải system roles)
     */
    public function scopeManageable($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope để lấy system roles
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Lấy danh sách roles có thể quản lý qua CRUD
     */
    public static function getManageableRoles()
    {
        return self::manageable()->orderBy('name')->get();
    }
}
