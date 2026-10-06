<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserRole;
use App\Models\SystemFunction;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $currentUser = Auth::user();
        
        // Kiểm tra quyền quản lý roles
        if (!$this->permissionService->canManageRoles($currentUser)) {
            abort(403, 'Bạn không có quyền quản lý roles.');
        }

        $roles = $this->permissionService->getManageableRoles($currentUser)->loadCount('users');
        $systemFunctions = SystemFunction::active()->get();
        $categories = $this->permissionService->getAllCategories();

        return view('admin.roles.index', compact('roles', 'systemFunctions', 'categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $currentUser = Auth::user();
        
        if (!$this->permissionService->canCreateRole($currentUser)) {
            abort(403, 'Bạn không có quyền tạo role mới.');
        }

        $systemFunctions = SystemFunction::active()->get();
        $categories = $this->permissionService->getAllCategories();

        return view('admin.roles.create', compact('systemFunctions', 'categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = Auth::user();
        
        if (!$this->permissionService->canCreateRole($currentUser)) {
            abort(403, 'Bạn không có quyền tạo role mới.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:user_roles,name',
            'display_name' => 'required|string|max:255',
            'can_create_users' => 'boolean',
            'can_assign_permissions' => 'boolean',
            'can_manage_geo_permissions' => 'boolean',
            'permissions' => 'nullable', // Chấp nhận cả string và array
        ]);

        // Xử lý permissions từ JSON string hoặc array
        $permissions = [];
        if ($request->has('permissions')) {
            if (is_string($request->permissions)) {
                $permissions = json_decode($request->permissions, true) ?? [];
            } elseif (is_array($request->permissions)) {
                $permissions = $request->permissions;
            }
        }

        DB::transaction(function () use ($validated, $permissions) {
            // Tạo role
            $role = UserRole::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'is_system' => false, // Role được tạo qua CRUD không phải system role
                'can_create_users' => $validated['can_create_users'] ?? false,
                'can_assign_permissions' => $validated['can_assign_permissions'] ?? false,
                'can_manage_geo_permissions' => $validated['can_manage_geo_permissions'] ?? false,
            ]);

            // Gán permissions
            if (!empty($permissions)) {
                $this->assignRolePermissions($role, $permissions);
            }
        });

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role đã được tạo thành công.');
    }

    /**
     * Display the specified resource.
     */
    public function show(UserRole $role): View
    {
        $currentUser = Auth::user();
        
        if (!$this->permissionService->canEditRole($currentUser, $role)) {
            abort(403, 'Bạn không có quyền xem role này.');
        }

        $permissions = $role->getAllPermissions();
        $systemFunctions = SystemFunction::active()->get();
        $categories = $this->permissionService->getAllCategories();

        return view('admin.roles.show', compact('role', 'permissions', 'systemFunctions', 'categories'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UserRole $role): View
    {
        $currentUser = Auth::user();
        
        if (!$this->permissionService->canEditRole($currentUser, $role)) {
            abort(403, 'Bạn không có quyền chỉnh sửa role này.');
        }

        $permissions = $role->getAllPermissions();
        $systemFunctions = SystemFunction::active()->get();
        $categories = $this->permissionService->getAllCategories();

        return view('admin.roles.edit', compact('role', 'permissions', 'systemFunctions', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UserRole $role): RedirectResponse
    {
        $currentUser = Auth::user();
        
        if (!$this->permissionService->canEditRole($currentUser, $role)) {
            abort(403, 'Bạn không có quyền chỉnh sửa role này.');
        }

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'can_create_users' => 'boolean',
            'can_assign_permissions' => 'boolean',
            'can_manage_geo_permissions' => 'boolean',
            'permissions' => 'nullable', // Chấp nhận cả string và array
        ]);

        // Xử lý permissions từ JSON string hoặc array
        $permissions = [];
        if ($request->has('permissions')) {
            if (is_string($request->permissions)) {
                $permissions = json_decode($request->permissions, true) ?? [];
            } elseif (is_array($request->permissions)) {
                $permissions = $request->permissions;
            }
        }

        DB::transaction(function () use ($role, $validated, $permissions) {
            // Cập nhật role
            $role->update([
                'display_name' => $validated['display_name'],
                'can_create_users' => $validated['can_create_users'] ?? false,
                'can_assign_permissions' => $validated['can_assign_permissions'] ?? false,
                'can_manage_geo_permissions' => $validated['can_manage_geo_permissions'] ?? false,
            ]);

            // Cập nhật permissions
            if (!empty($permissions)) {
                $this->assignRolePermissions($role, $permissions);
            }
        });

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role đã được cập nhật thành công.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UserRole $role): RedirectResponse
    {
        $currentUser = Auth::user();
        
        if (!$this->permissionService->canDeleteRole($currentUser, $role)) {
            abort(403, 'Bạn không có quyền xóa role này.');
        }

        // Kiểm tra xem role có đang được sử dụng không
        if ($role->users()->count() > 0) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Không thể xóa role đang được sử dụng bởi ' . $role->users()->count() . ' user(s).');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role đã được xóa thành công.');
    }

    /**
     * API: Lấy permissions của role
     */
    public function getPermissions(UserRole $role): JsonResponse
    {
        $currentUser = Auth::user();
        
        if (!$this->permissionService->canEditRole($currentUser, $role)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền xem permissions của role này.'
            ], 403);
        }

        $permissions = $role->getAllPermissions();

        return response()->json([
            'ok' => true,
            'data' => [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'is_system' => $role->is_system,
                ],
                'permissions' => $permissions
            ]
        ]);
    }

    /**
     * API: Cập nhật permissions của role
     */
    public function updatePermissions(Request $request, UserRole $role): JsonResponse
    {
        $currentUser = Auth::user();
        
        if (!$this->permissionService->canEditRole($currentUser, $role)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền cập nhật permissions của role này.'
            ], 403);
        }

        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.view' => 'boolean',
            'permissions.*.create' => 'boolean',
            'permissions.*.edit' => 'boolean',
            'permissions.*.delete' => 'boolean',
        ]);

        DB::transaction(function () use ($role, $validated) {
            $this->assignRolePermissions($role, $validated['permissions']);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Permissions đã được cập nhật thành công.'
        ]);
    }

    /**
     * Gán permissions cho role
     */
    private function assignRolePermissions(UserRole $role, array $permissions): void
    {
        // Xóa permissions cũ
        $role->functions()->detach();

        // Gán permissions mới
        foreach ($permissions as $functionCode => $perms) {
            $function = SystemFunction::where('code', $functionCode)->first();
            if (!$function) continue;

            $role->functions()->attach($function->id, [
                'can_view' => $perms['view'] ?? false,
                'can_create' => $perms['create'] ?? false,
                'can_edit' => $perms['edit'] ?? false,
                'can_delete' => $perms['delete'] ?? false,
            ]);
        }
    }

    /**
     * Bulk delete roles
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $currentUser = Auth::user();
        
        // Kiểm tra quyền quản lý roles
        if (!$this->permissionService->canManageRoles($currentUser)) {
            abort(403, 'Bạn không có quyền quản lý roles.');
        }

        $roleIds = explode(',', $request->input('role_ids', ''));
        
        if (empty($roleIds) || $roleIds[0] === '') {
            return redirect()->back()->with('error', 'Không có vai trò nào được chọn để xóa.');
        }

        // Lọc bỏ các ID không hợp lệ
        $roleIds = array_filter($roleIds, 'is_numeric');
        
        if (empty($roleIds)) {
            return redirect()->back()->with('error', 'Không có vai trò hợp lệ nào được chọn.');
        }

        try {
            DB::beginTransaction();

            // Lấy danh sách roles để kiểm tra
            $roles = UserRole::whereIn('id', $roleIds)->withCount('users')->get();
            
            // Kiểm tra xem có role hệ thống không
            $systemRoles = $roles->where('is_system', true);
            if ($systemRoles->count() > 0) {
                return redirect()->back()->with('error', 'Không thể xóa các vai trò hệ thống: ' . $systemRoles->pluck('display_name')->join(', '));
            }

            // Kiểm tra xem có role đang được sử dụng không
            $rolesInUse = $roles->where('users_count', '>', 0);
            if ($rolesInUse->count() > 0) {
                return redirect()->back()->with('error', 'Không thể xóa các vai trò đang được sử dụng: ' . $rolesInUse->pluck('display_name')->join(', '));
            }

            // Xóa roles
            $deletedCount = UserRole::whereIn('id', $roleIds)->delete();

            DB::commit();

            return redirect()->back()->with('success', "Đã xóa thành công {$deletedCount} vai trò.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Có lỗi xảy ra khi xóa vai trò: ' . $e->getMessage());
        }
    }
}