<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserRole;
use App\Models\SystemFunction;
use App\Models\UserFunctionPermission;
use App\Models\RoleFunctionPermission;
use App\Models\GeoUnit;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;

class PermissionService
{
    /**
     * Phân quyền chức năng cho user
     */
    public function assignUserPermissions(int $userId, array $permissions): bool
    {
        $user = User::findOrFail($userId);
        
        // Xóa quyền cũ
        UserFunctionPermission::where('user_id', $userId)->delete();
        
        // Tạo quyền mới
        foreach ($permissions as $functionCode => $perms) {
            $function = SystemFunction::where('code', $functionCode)->first();
            if (!$function) continue;
            
            UserFunctionPermission::create([
                'user_id' => $userId,
                'function_id' => $function->id,
                'can_view' => $perms['view'] ?? false,
                'can_create' => $perms['create'] ?? false,
                'can_edit' => $perms['edit'] ?? false,
                'can_delete' => $perms['delete'] ?? false,
            ]);
        }
        
        return true;
    }

    /**
     * Cập nhật quyền chức năng cho user
     */
    public function updateUserPermissions(int $userId, array $permissions): bool
    {
        $user = User::findOrFail($userId);
        
        foreach ($permissions as $functionCode => $perms) {
            $function = SystemFunction::where('code', $functionCode)->first();
            if (!$function) continue;
            
            $userPermission = UserFunctionPermission::where('user_id', $userId)
                ->where('function_id', $function->id)
                ->first();
            
            if ($userPermission) {
                $userPermission->updatePermissions($perms);
            } else {
                UserFunctionPermission::create([
                    'user_id' => $userId,
                    'function_id' => $function->id,
                    'can_view' => $perms['view'] ?? false,
                    'can_create' => $perms['create'] ?? false,
                    'can_edit' => $perms['edit'] ?? false,
                    'can_delete' => $perms['delete'] ?? false,
                ]);
            }
        }
        
        return true;
    }

    /**
     * Xóa quyền chức năng của user
     */
    public function removeUserPermissions(int $userId, array $functionCodes = []): bool
    {
        $query = UserFunctionPermission::where('user_id', $userId);
        
        if (!empty($functionCodes)) {
            $functionIds = SystemFunction::whereIn('code', $functionCodes)->pluck('id');
            $query->whereIn('function_id', $functionIds);
        }
        
        return $query->delete() > 0;
    }

    /**
     * Lấy quyền chức năng của user
     */
    public function getUserPermissions(int $userId): array
    {
        $user = User::findOrFail($userId);
        return $user->getAllPermissions();
    }

    /**
     * Kiểm tra user có quyền với chức năng không
     */
    public function hasPermission(int $userId, string $functionCode, string $action = 'view'): bool
    {
        $user = User::findOrFail($userId);
        return $user->hasPermission($functionCode, $action);
    }

    /**
     * Lấy danh sách chức năng có thể phân quyền cho user (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function getAssignableFunctions(User $currentUser): Collection
    {
        // Sử dụng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng
        $assignableFunctions = collect();
        
        foreach (SystemFunction::active()->get() as $function) {
            if ($currentUser->hasPermission($function->code, 'create') || 
                $currentUser->hasPermission($function->code, 'edit')) {
                $assignableFunctions->push($function);
            }
        }
        
        return $assignableFunctions;
    }

    /**
     * Lấy danh sách role có thể cấp cho user (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function getAssignableRoles(User $currentUser): Collection
    {
        // Sử dụng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng
        // Tạm thời trả về tất cả role (có thể điều chỉnh sau)
        return UserRole::orderBy('level', 'desc')->get();
    }

    /**
     * Kiểm tra user có thể phân quyền cho user khác không (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function canAssignPermissions(User $assigner, User $targetUser): bool
    {
        // Không thể phân quyền cho chính mình
        if ($assigner->id === $targetUser->id) {
            return false;
        }

        // Sử dụng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng
        return $assigner->canSee($targetUser);
    }

    /**
     * Lấy thống kê phân quyền
     */
    public function getPermissionStats(): array
    {
        $totalUsers = User::count();
        $usersByRole = User::with('userRole')
            ->get()
            ->groupBy(function($user) {
                return $user->userRole ? $user->userRole->name : 'unknown';
            })
            ->map->count();

        $functionStats = SystemFunction::withCount([
            'users as user_permissions_count',
            'roles as role_permissions_count'
        ])->get();

        return [
            'total_users' => $totalUsers,
            'users_by_role' => $usersByRole,
            'function_stats' => $functionStats,
            'total_functions' => SystemFunction::active()->count(),
        ];
    }


    /**
     * Lấy query builder cho danh sách user có thể quản lý bởi user hiện tại
     * Chỉ dùng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng (trừ role_id=1 không hiển thị)
     */
    public function getManageableUsersQuery(User $currentUser)
    {
        // Lấy tất cả user và lọc theo quyền xem (chỉ dùng quy tắc 3 cấp độ địa bàn)
        $allUsers = User::with(['userRole', 'province', 'ward', 'school'])->get();
        
        $accessibleUserIds = $allUsers->filter(function($user) use ($currentUser) {
            return $currentUser->canSee($user);
        })->pluck('id');
        
        return User::with(['userRole', 'province', 'ward', 'school'])
            ->whereIn('id', $accessibleUserIds);
    }

    /**
     * Tạo user với role và phân quyền
     */
    public function createUserWithRoleAndPermissions(array $userData, string $roleName, array $permissions = []): User
    {
        $role = UserRole::where('name', $roleName)->firstOrFail();
        
        // Tạo user
        $user = User::create(array_merge($userData, [
            'role_id' => $role->id,
            'is_active' => true,
        ]));
        
        // Phân quyền chức năng nếu có
        if (!empty($permissions)) {
            $this->assignUserPermissions($user->id, $permissions);
        }
        
        return $user;
    }

    /**
     * Cập nhật role và phân quyền cho user
     */
    public function updateUserRoleAndPermissions(int $userId, string $roleName, array $permissions = []): bool
    {
        $user = User::findOrFail($userId);
        $role = UserRole::where('name', $roleName)->firstOrFail();
        
        // Cập nhật role
        $user->update(['role_id' => $role->id]);
        
        // Cập nhật phân quyền nếu có
        if (!empty($permissions)) {
            $this->assignUserPermissions($userId, $permissions);
        }
        
        return true;
    }

    /**
     * Lấy danh sách chức năng theo category
     */
    public function getFunctionsByCategory(string $category = null): Collection
    {
        $query = SystemFunction::active();
        
        if ($category) {
            $query->where('category', $category);
        }
        
        return $query->orderBy('name')->get();
    }

    /**
     * Lấy tất cả categories
     */
    public function getAllCategories(): array
    {
        return SystemFunction::getAllCategories();
    }

    // ==================== ROLE MANAGEMENT ====================

    /**
     * Kiểm tra user có role không
     */
    public function hasRole(?User $user, string|array $roles): bool
    {
        if (!$user) return false;
        
        // Superadmin luôn có tất cả quyền
        if ($user->isSuperadmin()) {
            return true;
        }
        
        $roles = is_array($roles) ? $roles : [$roles];
        
        // Kiểm tra theo role-based system mới
        if ($user->userRole) {
            return in_array($user->userRole->name, $roles, true);
        }
        
        // Fallback cho hệ thống cũ
        return in_array($user->role, $roles, true);
    }

    /**
     * Kiểm tra user có phải admin không
     */
    public function isAdmin(?User $user): bool
    {
        if (!$user) return false;
        
        // Superadmin luôn là admin
        if ($user->isSuperadmin()) {
            return true;
        }
        
        // Kiểm tra theo role-based system mới
        if ($user->userRole) {
            return $user->userRole->name === 'admin';
        }
        
        // Fallback cho hệ thống cũ
        return $this->hasRole($user, ['admin']);
    }

    /**
     * Yêu cầu quyền admin, nếu không có thì abort 403
     */
    public function requireAdmin(?User $user): void
    {
        abort_unless($this->isAdmin($user), 403, 'Chỉ admin được thực hiện hành động này.');
    }

    /**
     * Lấy level của role
     */
    public function getRoleLevel(User $user): int
    {
        if (!$user->userRole) return 0;
        return $user->userRole->level ?? 0;
    }

    /**
     * Kiểm tra user có thể quản lý role không
     */
    public function canManageRoles(?User $user): bool
    {
        if (!$user) return false;
        
        // Superadmin luôn có thể quản lý roles
        if ($user->isSuperadmin()) {
            return true;
        }
        
        // Kiểm tra quyền phân quyền
        return $user->canAssignPermissions();
    }

    /**
     * Kiểm tra user có thể tạo role mới không
     */
    public function canCreateRole(?User $user): bool
    {
        return $this->canManageRoles($user);
    }

    /**
     * Kiểm tra user có thể chỉnh sửa role không
     */
    public function canEditRole(?User $user, UserRole $role): bool
    {
        if (!$user) return false;
        
        // Superadmin có thể chỉnh sửa tất cả roles
        if ($user->isSuperadmin()) {
            return true;
        }
        
        // Không thể chỉnh sửa system roles
        if ($role->isSystemRole()) {
            return false;
        }
        
        // Kiểm tra quyền phân quyền
        return $user->canAssignPermissions();
    }

    /**
     * Kiểm tra user có thể xóa role không
     */
    public function canDeleteRole(?User $user, UserRole $role): bool
    {
        if (!$user) return false;
        
        // Superadmin có thể xóa tất cả roles (trừ superadmin)
        if ($user->isSuperadmin()) {
            return !$role->isSuperadmin();
        }
        
        // Không thể xóa system roles
        if ($role->isSystemRole()) {
            return false;
        }
        
        // Kiểm tra quyền phân quyền
        return $user->canAssignPermissions();
    }

    /**
     * Lấy danh sách roles có thể quản lý
     */
    public function getManageableRoles(?User $user): Collection
    {
        if (!$user) return collect();
        
        // Superadmin có thể quản lý tất cả roles (trừ superadmin)
        if ($user->isSuperadmin()) {
            return UserRole::where('name', '!=', 'superadmin')->get();
        }
        
        // Các user khác chỉ có thể quản lý non-system roles
        return UserRole::manageable()->get();
    }

    // ==================== GEO-BASED PERMISSIONS ====================

    /**
     * Lấy danh sách user có thể quản lý bởi user hiện tại (theo quy tắc 3 cấp độ địa bàn)
     * Chỉ dùng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng (trừ role_id=1 không hiển thị)
     */
    public function getManageableUsers(User $currentUser): Collection
    {
        // Lấy tất cả user và lọc theo quyền xem (chỉ dùng quy tắc 3 cấp độ địa bàn)
        $allUsers = User::with(['userRole', 'province', 'ward', 'school'])->get();
        
        return $allUsers->filter(function($user) use ($currentUser) {
            return $currentUser->canSee($user);
        });
    }

    /**
     * Kiểm tra user có thể quản lý user khác không (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function canManageUser(User $manager, User $targetUser): bool
    {
        return $manager->canSee($targetUser);
    }

    /**
     * Lấy danh sách provinces có thể phân quyền cho user (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function getAssignableProvinces(User $user): Collection
    {
        // Admin có thể phân quyền cho tất cả tỉnh (chỉ lấy scheme = 'new')
        if ($user->isAdmin()) {
            return GeoUnit::where('type_level', 1)->where('scheme', 'new')->orderBy('name')->get();
        }
        
        // User cấp tỉnh chỉ có thể phân quyền cho tỉnh của mình
        if ($user->isProvince()) {
            return GeoUnit::where('id', $user->province_id)->where('scheme', 'new')->get();
        }
        
        // User cấp xã có thể phân quyền cho tỉnh chứa xã của mình
        if ($user->isWard()) {
            return GeoUnit::where('id', $user->province_id)->where('scheme', 'new')->get();
        }
        
        // User cấp trường có thể phân quyền cho tỉnh chứa trường của mình
        if ($user->isSchool()) {
            return GeoUnit::where('id', $user->province_id)->where('scheme', 'new')->get();
        }
        
        return collect();
    }

    /**
     * Lấy danh sách wards có thể phân quyền cho user (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function getAssignableWards(User $user): Collection
    {
        // Admin có thể phân quyền cho tất cả xã
        if ($user->isAdmin()) {
            return GeoUnit::where('type_level', 3)->orderBy('name')->get();
        }
        
        // User cấp tỉnh có thể phân quyền cho tất cả xã trong tỉnh
        if ($user->isProvince()) {
            return GeoUnit::where('parent_id', $user->province_id)
                ->where('type_level', 3)
                ->orderBy('name')
                ->get();
        }
        
        // User cấp xã chỉ có thể phân quyền cho xã của mình
        if ($user->isWard()) {
            return GeoUnit::where('id', $user->ward_id)->get();
        }
        
        // User cấp trường có thể phân quyền cho xã chứa trường của mình
        if ($user->isSchool()) {
            return GeoUnit::where('id', $user->ward_id)->get();
        }
        
        return collect();
    }

    /**
     * Lấy danh sách schools có thể phân quyền cho user (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function getAssignableSchools(User $user): Collection
    {
        // Admin có thể phân quyền cho tất cả trường
        if ($user->isAdmin()) {
            return School::orderBy('name')->get();
        }
        
        // User cấp tỉnh có thể phân quyền cho tất cả trường trong tỉnh
        if ($user->isProvince()) {
            // Lấy tất cả ward codes thuộc tỉnh này
            $wardCodes = GeoUnit::where('parent_id', $user->province_id)
                ->where('type_level', 3)
                ->where('scheme', 'new')
                ->pluck('code')
                ->toArray();
            
            return School::whereIn('ward_code_new', $wardCodes)
                ->orderBy('name')
                ->get();
        }
        
        // User cấp xã có thể phân quyền cho tất cả trường trong xã
        if ($user->isWard()) {
            // Lấy ward code từ ward_id
            $ward = GeoUnit::find($user->ward_id);
            if (!$ward) {
                return collect();
            }
            
            return School::where('ward_code_new', $ward->code)
                ->orderBy('name')
                ->get();
        }
        
        // User cấp trường chỉ có thể phân quyền cho trường của mình
        if ($user->isSchool()) {
            return School::where('id', $user->school_id)->get();
        }
        
        return collect();
    }

    /**
     * Gán role level cho user (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function assignRoleLevel(User $user, string $roleLevel, ?int $provinceId = null, ?int $wardId = null, ?int $schoolId = null): bool
    {
        // Debug logging
        \Illuminate\Support\Facades\Log::info('assignRoleLevel called:', [
            'user_id' => $user->id,
            'role_level' => $roleLevel,
            'province_id' => $provinceId,
            'ward_id' => $wardId,
            'school_id' => $schoolId
        ]);

        $data = ['role_level' => $roleLevel];
        
        switch ($roleLevel) {
            case 'admin':
                // Admin không cần province_id, ward_id, school_id
                $data['province_id'] = null;
                $data['ward_id'] = null;
                $data['school_id'] = null;
                break;
            case 'province':
                if (!$provinceId) return false;
                // Chỉ cần lưu province_id
                $data['province_id'] = $provinceId;
                $data['ward_id'] = null;
                $data['school_id'] = null;
                break;
            case 'ward':
                if (!$wardId) return false;
                // Chỉ cần lưu ward_id, không cần lấy province_id
                $data['province_id'] = null;
                $data['ward_id'] = $wardId;
                $data['school_id'] = null;
                break;
            case 'school':
                if (!$schoolId) return false;
                // Lấy province_id và ward_id từ school
                $school = School::find($schoolId);
                if (!$school) return false;
                
                $data['province_id'] = $school->province_id;
                $data['ward_id'] = $school->ward_id;
                $data['school_id'] = $schoolId;
                break;
            default:
                return false;
        }
        
        // Debug logging trước khi update
        \Illuminate\Support\Facades\Log::info('assignRoleLevel data to update:', [
            'user_id' => $user->id,
            'data' => $data,
            'data_types' => [
                'province_id' => gettype($data['province_id'] ?? null),
                'ward_id' => gettype($data['ward_id'] ?? null),
                'school_id' => gettype($data['school_id'] ?? null),
            ]
        ]);
        
        $result = $user->update($data);
        
        // Debug logging sau khi update
        \Illuminate\Support\Facades\Log::info('assignRoleLevel result:', [
            'user_id' => $user->id,
            'success' => $result,
            'user_after' => $user->fresh()->toArray(),
            'user_after_specific' => [
                'province_id' => $user->fresh()->province_id,
                'ward_id' => $user->fresh()->ward_id,
                'school_id' => $user->fresh()->school_id,
            ]
        ]);
        
        return $result;
    }

    /**
     * Lấy thống kê phân quyền theo role (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function getRoleStats(): array
    {
        $totalUsers = User::count();
        $usersWithRole = User::whereNotNull('role_level')->count();
        $usersWithoutRole = $totalUsers - $usersWithRole;

        $roleStats = User::whereNotNull('role_level')
            ->selectRaw('role_level, COUNT(*) as count')
            ->groupBy('role_level')
            ->pluck('count', 'role_level')
            ->toArray();

        return [
            'total_users' => $totalUsers,
            'users_with_role' => $usersWithRole,
            'users_without_role' => $usersWithoutRole,
            'by_role' => [
                'admin' => $roleStats['admin'] ?? 0,
                'province' => $roleStats['province'] ?? 0,
                'ward' => $roleStats['ward'] ?? 0,
                'school' => $roleStats['school'] ?? 0,
            ]
        ];
    }

    // ==================== HIERARCHICAL PERMISSIONS ====================

    /**
     * Kiểm tra quyền phân cấp (4 cấp: admin > province > ward > school)
     */
    public function hasHierarchicalPermission(User $user, string $functionCode, string $action = 'view'): bool
    {
        return $user->hasPermission($functionCode, $action);
    }

    /**
     * Lấy danh sách ID có thể truy cập theo phân quyền phân cấp (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    public function getAccessibleIds(User $user, string $type): array
    {
        // Sử dụng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng
        $allUsers = User::with(['userRole', 'province', 'ward', 'school'])->get();
        
        $accessibleUsers = $allUsers->filter(function($targetUser) use ($user) {
            return $user->canSee($targetUser);
        });

        switch ($type) {
            case 'school':
                return School::pluck('id')->toArray();
            case 'user':
                return $accessibleUsers->pluck('id')->toArray();
            default:
                return [];
        }
    }

    /**
     * Lấy danh sách ID trường học có thể truy cập (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    private function getAccessibleSchoolIds(User $user, $geoPermission): array
    {
        // Sử dụng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng
        if ($user->isProvinceLevel()) {
            return School::where('province_id', $user->province_id)->pluck('id')->toArray();
        }
        
        if ($user->isWardLevel()) {
            return School::where('ward_id', $user->ward_id)->pluck('id')->toArray();
        }
        
        if ($user->isSchoolLevel()) {
            return School::where('id', $user->school_id)->pluck('id')->toArray();
        }
        
        return [];
    }

    /**
     * Lấy danh sách ID user có thể truy cập (sử dụng quy tắc 3 cấp độ địa bàn)
     */
    private function getAccessibleUserIds(User $user, $geoPermission): array
    {
        // Sử dụng quy tắc 3 cấp độ địa bàn, role_id không ảnh hưởng
        $allUsers = User::with(['userRole', 'province', 'ward', 'school'])->get();
        
        $accessibleUsers = $allUsers->filter(function($targetUser) use ($user) {
            return $user->canSee($targetUser);
        });
        
        return $accessibleUsers->pluck('id')->toArray();
    }
}
