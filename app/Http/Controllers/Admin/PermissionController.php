<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRole;
use App\Models\SystemFunction;
use App\Models\FunctionCategory;
use App\Models\RoleFunctionPermission;
use App\Models\UserFunctionPermission;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * Hiển thị trang quản lý phân quyền
     */
    public function index(): View
    {
        $currentUser = Auth::user();
        $users = $this->permissionService->getManageableUsers($currentUser);
        $roles = UserRole::getSelectableRoles();
        $functions = SystemFunction::active()->get();
        $categories = $this->permissionService->getAllCategories();
        $stats = $this->permissionService->getPermissionStats();
        
        return view('admin.permissions.index', compact('users', 'roles', 'functions', 'categories', 'stats'));
    }

    /**
     * API: Lấy quyền của user
     */
    public function getUserPermissions(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $permissions = $this->permissionService->getUserPermissions($userId);
        
        return response()->json([
            'ok' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->userRole?->name ?? 'unknown',
                    'role_display' => $user->getRoleDisplayName(),
                ],
                'permissions' => $permissions
            ]
        ]);
    }

    /**
     * API: Phân quyền chức năng cho user
     */
    public function assignUserPermissions(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'permissions' => 'required', // Chấp nhận cả string và array
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        }

        // Parse permissions từ JSON string hoặc array
        $permissions = [];
        if (is_string($request->permissions)) {
            $permissions = json_decode($request->permissions, true) ?? [];
        } elseif (is_array($request->permissions)) {
            $permissions = $request->permissions;
        }

        // Normalize permissions - đảm bảo tất cả đều có giá trị boolean
        $normalizedPermissions = [];
        foreach ($permissions as $functionCode => $perms) {
            if (is_array($perms)) {
                $normalizedPermissions[$functionCode] = [
                    'view' => isset($perms['view']) ? (bool)$perms['view'] : false,
                    'create' => isset($perms['create']) ? (bool)$perms['create'] : false,
                    'edit' => isset($perms['edit']) ? (bool)$perms['edit'] : false,
                    'delete' => isset($perms['delete']) ? (bool)$perms['delete'] : false,
                ];
            }
        }

        $currentUser = Auth::user();
        $targetUser = User::findOrFail($validated['user_id']);

        // Kiểm tra quyền xem user (theo quy tắc 3 cấp độ địa bàn)
        if (!$currentUser->canSee($targetUser)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền xem người dùng này.'
            ], 403);
        }

        // Kiểm tra quyền phân quyền
        if (!$this->permissionService->canAssignPermissions($currentUser, $targetUser)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền phân quyền cho người dùng này.'
            ], 403);
        }

        try {
            $this->permissionService->assignUserPermissions($validated['user_id'], $normalizedPermissions);
            
            return response()->json([
                'ok' => true,
                'message' => 'Phân quyền chức năng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Có lỗi xảy ra khi phân quyền: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Cập nhật quyền chức năng cho user
     */
    public function updateUserPermissions(Request $request, int $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'permissions' => 'required', // Chấp nhận cả string và array
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        }

        // Parse permissions từ JSON string hoặc array
        $permissions = [];
        if (is_string($request->permissions)) {
            $permissions = json_decode($request->permissions, true) ?? [];
        } elseif (is_array($request->permissions)) {
            $permissions = $request->permissions;
        }

        // Normalize permissions - đảm bảo tất cả đều có giá trị boolean
        $normalizedPermissions = [];
        foreach ($permissions as $functionCode => $perms) {
            if (is_array($perms)) {
                $normalizedPermissions[$functionCode] = [
                    'view' => isset($perms['view']) ? (bool)$perms['view'] : false,
                    'create' => isset($perms['create']) ? (bool)$perms['create'] : false,
                    'edit' => isset($perms['edit']) ? (bool)$perms['edit'] : false,
                    'delete' => isset($perms['delete']) ? (bool)$perms['delete'] : false,
                ];
            }
        }

        $currentUser = Auth::user();
        $targetUser = User::findOrFail($userId);

        // Kiểm tra quyền xem user (theo quy tắc 3 cấp độ địa bàn)
        if (!$currentUser->canSee($targetUser)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền xem người dùng này.'
            ], 403);
        }

        // Kiểm tra quyền phân quyền
        if (!$this->permissionService->canAssignPermissions($currentUser, $targetUser)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền cập nhật quyền cho người dùng này.'
            ], 403);
        }

        try {
            $this->permissionService->updateUserPermissions($userId, $normalizedPermissions);
            
            return response()->json([
                'ok' => true,
                'message' => 'Cập nhật quyền chức năng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật quyền: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Xóa quyền chức năng của user
     */
    public function removeUserPermissions(Request $request, int $userId): JsonResponse
    {
        $validated = $request->validate([
            'function_codes' => 'nullable|array',
            'function_codes.*' => 'string|exists:system_functions,code',
        ]);

        $currentUser = Auth::user();
        $targetUser = User::findOrFail($userId);

        // Kiểm tra quyền xem user (theo quy tắc 3 cấp độ địa bàn)
        if (!$currentUser->canSee($targetUser)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền xem người dùng này.'
            ], 403);
        }

        // Kiểm tra quyền phân quyền
        if (!$this->permissionService->canAssignPermissions($currentUser, $targetUser)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền xóa quyền của người dùng này.'
            ], 403);
        }

        try {
            $this->permissionService->removeUserPermissions($userId, $validated['function_codes'] ?? []);
            
            return response()->json([
                'ok' => true,
                'message' => 'Xóa quyền chức năng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Có lỗi xảy ra khi xóa quyền: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Lấy danh sách chức năng có thể phân quyền
     */
    public function getAssignableFunctions(): JsonResponse
    {
        $currentUser = Auth::user();
        $functions = $this->permissionService->getAssignableFunctions($currentUser);
        
        return response()->json([
            'ok' => true,
            'data' => $functions->map(function($function) {
                return [
                    'id' => $function->id,
                    'code' => $function->code,
                    'name' => $function->name,
                    'category' => $function->category,
                    'description' => $function->description,
                ];
            })
        ]);
    }

    /**
     * API: Lấy danh sách role có thể cấp
     */
    public function getAssignableRoles(): JsonResponse
    {
        $currentUser = Auth::user();
        $roles = $this->permissionService->getAssignableRoles($currentUser);
        
        return response()->json([
            'ok' => true,
            'data' => $roles->map(function($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'level' => $role->level,
                    'can_create_users' => $role->can_create_users,
                    'can_assign_permissions' => $role->can_assign_permissions,
                    'can_manage_geo_permissions' => $role->can_manage_geo_permissions,
                ];
            })
        ]);
    }

    /**
     * API: Lấy thống kê phân quyền
     */
    public function getStats(): JsonResponse
    {
        $stats = $this->permissionService->getPermissionStats();
        
        return response()->json([
            'ok' => true,
            'data' => $stats
        ]);
    }

    /**
     * API: Tạo user với role và phân quyền
     */
    public function createUserWithPermissions(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone_number' => 'nullable|string|max:20',
                'role' => 'required|string|exists:user_roles,name',
                'permissions' => 'nullable', // Chấp nhận cả string và array
                'ward_code' => 'nullable|string',
                'district_code' => 'nullable|string', 
                'ward_code_new' => 'nullable|string',
                'geo_note' => 'nullable|string|max:500',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        }

        // Parse permissions từ JSON string hoặc array
        $permissions = [];
        if ($request->has('permissions')) {
            if (is_string($request->permissions)) {
                $permissions = json_decode($request->permissions, true) ?? [];
            } elseif (is_array($request->permissions)) {
                $permissions = $request->permissions;
            }
        }

        // Normalize permissions - đảm bảo tất cả đều có giá trị boolean
        $normalizedPermissions = [];
        foreach ($permissions as $functionCode => $perms) {
            if (is_array($perms)) {
                $normalizedPermissions[$functionCode] = [
                    'view' => isset($perms['view']) ? (bool)$perms['view'] : false,
                    'create' => isset($perms['create']) ? (bool)$perms['create'] : false,
                    'edit' => isset($perms['edit']) ? (bool)$perms['edit'] : false,
                    'delete' => isset($perms['delete']) ? (bool)$perms['delete'] : false,
                ];
            }
        }

        $currentUser = Auth::user();
        
        // Kiểm tra quyền tạo user (role_id=1 hoặc role_id=2)
        if (!$currentUser || ($currentUser->role_id != 1 && $currentUser->role_id != 2)) {
            return response()->json([
                'ok' => false,
                'message' => 'Bạn không có quyền tạo người dùng.'
            ], 403);
        }

        try {
            // Tạo user
            $user = $this->permissionService->createUserWithRoleAndPermissions(
                [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone_number' => $validated['phone_number'] ?? null,
                ],
                $validated['role'],
                $normalizedPermissions
            );


            return response()->json([
                'ok' => true,
                'message' => 'Tạo người dùng và phân quyền thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Có lỗi xảy ra khi tạo người dùng: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hiển thị trang quản lý chức năng
     */
    public function functions(Request $request): View
    {
        $query = SystemFunction::query();
        
        // Áp dụng bộ lọc tìm kiếm
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('code', 'like', "%{$searchTerm}%");
            });
        }
        
        // Áp dụng bộ lọc danh mục
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        
        $functions = $query->orderBy('category')->orderBy('name')->get();
        $categories = $this->permissionService->getAllCategories();
        
        return view('admin.permissions.functions', compact('functions', 'categories'));
    }

    /**
     * Hiển thị trang phân quyền cho user cụ thể
     */
    public function userPermissions($user): View
    {
        $user = User::findOrFail($user);
        $currentUser = Auth::user();
        
        // Lấy tất cả permissions của user được phân quyền
        $permissions = $user->getAllPermissions();
        
        // Lấy permissions của user hiện tại để chỉ hiển thị những quyền họ có
        $currentUserPermissions = $currentUser->getAllPermissions();
        
        // Lọc functions chỉ hiển thị những quyền mà current user có
        $allFunctions = SystemFunction::active()->get();
        $assignableFunctions = collect();
        
        foreach ($allFunctions as $function) {
            $hasAnyPermission = false;
            if (isset($currentUserPermissions[$function->code])) {
                $currentPerms = $currentUserPermissions[$function->code];
                if (is_array($currentPerms)) {
                    $hasAnyPermission = ($currentPerms['view'] ?? false) || 
                                      ($currentPerms['create'] ?? false) || 
                                      ($currentPerms['edit'] ?? false) || 
                                      ($currentPerms['delete'] ?? false);
                }
            }
            
            if ($hasAnyPermission) {
                $assignableFunctions->push($function);
            }
        }
        
        $functions = $assignableFunctions;
        $categories = $this->permissionService->getAllCategories();
        
        return view('admin.permissions.user', compact('user', 'permissions', 'functions', 'categories'));
    }

    /**
     * Hiển thị trang xem thông tin phân quyền
     */
    public function viewPermissions($user): View
    {
        $user = User::findOrFail($user);
        
        // Lấy tất cả permissions (bao gồm cả role và user permissions)
        $permissions = $user->getAllPermissions();
        
        $functions = SystemFunction::active()->get();
        $categories = $this->permissionService->getAllCategories();
        
        // Tính toán thống kê
        $totalFunctions = $functions->count();
        $permissionCount = 0;
        foreach ($permissions as $functionCode => $perms) {
            if (is_array($perms) && ($perms['view'] || $perms['create'] || $perms['edit'] || $perms['delete'])) {
                $permissionCount++;
            }
        }
        $permissionPercentage = $totalFunctions > 0 ? round(($permissionCount / $totalFunctions) * 100, 1) : 0;
        
        return view('admin.permissions.view', compact('user', 'permissions', 'functions', 'categories', 'totalFunctions', 'permissionCount', 'permissionPercentage'));
    }

    /**
     * Xử lý form phân quyền cho user (redirect response)
     */
    public function assignUserPermissionsForm(Request $request, $user): RedirectResponse
    {
        try {
            $user = User::findOrFail($user);
            $currentUser = Auth::user();
            
            // Lấy permissions của user hiện tại để kiểm tra quyền phân quyền
            $currentUserPermissions = $currentUser->getAllPermissions();
            
            // Lấy tất cả functions
            $functions = \App\Models\SystemFunction::active()->get();
            
            // Khởi tạo permissions với tất cả false
            $normalizedPermissions = [];
            foreach ($functions as $function) {
                $normalizedPermissions[$function->code] = [
                    'view' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                ];
            }
            
            // Xử lý permissions từ form
            if ($request->has('permissions')) {
                $formPermissions = $request->input('permissions');
                
                foreach ($formPermissions as $functionCode => $actions) {
                    if (isset($normalizedPermissions[$functionCode]) && is_array($actions)) {
                        // Kiểm tra xem current user có quyền phân quyền cho function này không
                        $currentUserHasPermission = false;
                        if (isset($currentUserPermissions[$functionCode])) {
                            $currentPerms = $currentUserPermissions[$functionCode];
                            if (is_array($currentPerms)) {
                                $currentUserHasPermission = ($currentPerms['view'] ?? false) || 
                                                          ($currentPerms['create'] ?? false) || 
                                                          ($currentPerms['edit'] ?? false) || 
                                                          ($currentPerms['delete'] ?? false);
                            }
                        }
                        
                        // Chỉ xử lý nếu current user có quyền
                        if ($currentUserHasPermission) {
                            foreach ($actions as $action => $value) {
                                if (in_array($action, ['view', 'create', 'edit', 'delete'])) {
                                    // Kiểm tra xem current user có quyền action này không
                                    $canAssignAction = isset($currentUserPermissions[$functionCode][$action]) && 
                                                     $currentUserPermissions[$functionCode][$action];
                                    
                                    if ($canAssignAction) {
                                        $normalizedPermissions[$functionCode][$action] = ($value === '1' || $value === 1 || $value === true);
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            $this->permissionService->assignUserPermissions($user->id, $normalizedPermissions);
            
            return redirect()->back()->with('success', 'Phân quyền chức năng thành công!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }

    /**
     * Tạo chức năng mới
     */
    public function createFunction(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:100|unique:system_functions,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|in:education,household,school,report,system',
            'is_active' => 'boolean'
        ]);

        try {
            $function = SystemFunction::create([
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'is_active' => $request->boolean('is_active', true)
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Tạo chức năng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thông tin chức năng
     */
    public function showFunction(int $id): JsonResponse
    {
        try {
            $function = SystemFunction::findOrFail($id);
            return response()->json([
                'ok' => true,
                'data' => $function
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Cập nhật chức năng
     */
    public function updateFunction(Request $request, int $id): JsonResponse
    {
        $function = SystemFunction::findOrFail($id);
        
        $request->validate([
            'code' => 'required|string|max:100|unique:system_functions,code,' . $id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|in:education,household,school,report,system',
            'is_active' => 'boolean'
        ]);

        try {
            $function->update([
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'is_active' => $request->boolean('is_active', true)
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Cập nhật chức năng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa chức năng
     */
    public function destroyFunction(int $id): JsonResponse
    {
        try {
            $function = SystemFunction::findOrFail($id);
            
            // Kiểm tra xem chức năng có đang được sử dụng không
            $hasRolePermissions = RoleFunctionPermission::where('function_id', $id)->exists();
            $hasUserPermissions = UserFunctionPermission::where('function_id', $id)->exists();
            
            if ($hasRolePermissions || $hasUserPermissions) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Không thể xóa chức năng đang được sử dụng'
                ], 400);
            }
            
            $function->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Xóa chức năng thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hiển thị form tạo chức năng mới
     */
    public function createFunctionForm(): View
    {
        $categories = $this->permissionService->getAllCategories();
        return view('admin.permissions.functions.create', compact('categories'));
    }

    /**
     * Xử lý tạo chức năng mới (form submission)
     */
    public function storeFunction(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|max:100|unique:system_functions,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        try {
            SystemFunction::create([
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return redirect()->route('admin.permissions.functions')
                ->with('success', 'Tạo chức năng thành công!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Hiển thị form chỉnh sửa chức năng
     */
    public function editFunctionForm($function): View
    {
        $function = SystemFunction::findOrFail($function);
        $categories = $this->permissionService->getAllCategories();
        
        return view('admin.permissions.functions.edit', compact('function', 'categories'));
    }

    /**
     * Xử lý cập nhật chức năng (form submission)
     */
    public function updateFunctionForm(Request $request, $function): RedirectResponse
    {
        $function = SystemFunction::findOrFail($function);
        
        $request->validate([
            'code' => 'required|string|max:100|unique:system_functions,code,' . $function->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        try {
            $function->update([
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'category' => $request->category,
                'is_active' => $request->boolean('is_active'),
            ]);

            return redirect()->route('admin.permissions.functions')
                ->with('success', 'Cập nhật chức năng thành công!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Xử lý xóa chức năng (form submission)
     */
    public function destroyFunctionForm($function): RedirectResponse
    {
        $function = SystemFunction::findOrFail($function);
        
        try {
            $function->delete();
            
            return redirect()->route('admin.permissions.functions')
                ->with('success', 'Xóa chức năng thành công!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }

    /**
     * Hiển thị trang quản lý danh mục chức năng
     */
    public function categories(Request $request): View
    {
        $query = FunctionCategory::query();
        
        // Áp dụng bộ lọc tìm kiếm
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('code', 'like', "%{$searchTerm}%");
            });
        }
        
        $categories = $query->ordered()->get();
        
        return view('admin.permissions.categories', compact('categories'));
    }

    /**
     * Hiển thị form tạo danh mục mới
     */
    public function createCategoryForm(): View
    {
        return view('admin.permissions.categories.create');
    }

    /**
     * Xử lý tạo danh mục mới (form submission)
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:function_categories,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        try {
            FunctionCategory::create([
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'color' => $request->color,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return redirect()->route('admin.permissions.categories')
                ->with('success', 'Tạo danh mục thành công!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Hiển thị form chỉnh sửa danh mục
     */
    public function editCategoryForm($category): View
    {
        $category = FunctionCategory::findOrFail($category);
        
        return view('admin.permissions.categories.edit', compact('category'));
    }

    /**
     * Xử lý cập nhật danh mục (form submission)
     */
    public function updateCategory(Request $request, $category): RedirectResponse
    {
        $category = FunctionCategory::findOrFail($category);
        
        $request->validate([
            'code' => 'required|string|max:50|unique:function_categories,code,' . $category->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        try {
            $category->update([
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'color' => $request->color,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->boolean('is_active'),
            ]);

            return redirect()->route('admin.permissions.categories')
                ->with('success', 'Cập nhật danh mục thành công!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Xử lý xóa danh mục (form submission)
     */
    public function destroyCategory($category): RedirectResponse
    {
        $category = FunctionCategory::findOrFail($category);
        
        try {
            // Kiểm tra xem có chức năng nào thuộc danh mục này không
            if ($category->functions()->count() > 0) {
                return redirect()->back()
                    ->withErrors(['error' => 'Không thể xóa danh mục này vì còn có chức năng thuộc danh mục.']);
            }
            
            $category->delete();
            
            return redirect()->route('admin.permissions.categories')
                ->with('success', 'Xóa danh mục thành công!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }
}
