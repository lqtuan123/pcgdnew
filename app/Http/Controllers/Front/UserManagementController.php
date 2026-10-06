<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use App\Services\PermissionService;
use App\Models\User;
use App\Models\UserRole;
use App\Models\SystemFunction;
use App\Models\FunctionCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Rules\NotSuperadminRole;

class UserManagementController extends Controller
{
    protected $userService;
    protected $permissionService;

    public function __construct(UserService $userService, PermissionService $permissionService)
    {
        $this->userService = $userService;
        $this->permissionService = $permissionService;
    }

    /**
     * Hiển thị danh sách người dùng
     */
    public function index(Request $request): View
    {
        // Bỏ kiểm tra quyền view theo yêu cầu - cho phép mọi người dùng đã đăng nhập xem danh sách
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Bạn cần đăng nhập để truy cập.');
        }
        
        // Sử dụng logic từ backend
        $usersQuery = $this->permissionService->getManageableUsersQuery($this->userService->getCurrentUser());
        
        // Áp dụng bộ lọc tìm kiếm
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $usersQuery->where(function($query) use ($searchTerm) {
                $query->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }
        
        // Áp dụng bộ lọc vai trò
        if ($request->filled('role')) {
            $usersQuery->whereHas('userRole', function($query) use ($request) {
                $query->where('name', $request->role);
            });
        }
        
        // Áp dụng bộ lọc danh mục chức năng
        if ($request->filled('category')) {
            $category = $request->category;
            $usersQuery->whereHas('functionPermissions', function($query) use ($category) {
                $query->where('category', $category);
            });
        }
        
        $users = $usersQuery->get();
        
        // Lấy thông tin về phạm vi vai trò
        $currentUser = $this->userService->getCurrentUser();
        $roleScope = null;
        
        if ($currentUser && !$currentUser->isAdmin()) {
            if ($currentUser->userRole) {
                $roleScope = "Vai trò: " . ($currentUser->userRole->display_name ?? $currentUser->userRole->name) . " (Level: " . ($currentUser->userRole->level ?? 'N/A') . ")";
            } else {
                $roleScope = "Chưa được phân quyền vai trò - Chỉ xem được chính mình";
            }
        }
        
        // Lấy dữ liệu cần thiết cho phân quyền (loại bỏ superadmin)
        $roles = UserRole::getSelectableRoles();
        $functions = SystemFunction::active()->get();
        $categories = FunctionCategory::active()->ordered()->get();
        
        return view('front.user-management.index', compact('users', 'roleScope', 'roles', 'functions', 'categories'));
    }

    /**
     * Hiển thị form tạo người dùng mới
     */
    public function create(): View
    {
        // Kiểm tra quyền tạo người dùng
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_nguoi_dung', 'create')) {
            abort(403, 'Bạn không có quyền tạo người dùng mới.');
        }
        
        $roles = UserRole::getSelectableRoles();
        
        // Lấy functions mà user hiện tại có quyền
        $currentUser = $this->userService->getCurrentUser();
        $currentUserPermissions = $currentUser->getAllPermissions();
        
        // Lấy danh sách vai trò mà user hiện tại có thể cấp cho user mới
        $assignableRoles = collect();
        foreach ($roles as $role) {
            // User có thể cấp vai trò nếu họ có quyền tạo người dùng và có thể cấp vai trò đó
            if ($currentUser->hasPermission('quan_ly_nguoi_dung', 'create') && $currentUser->canGrantRole($role->name)) {
                $assignableRoles->push($role);
            }
        }
        
        // Cập nhật $roles để chỉ chứa những vai trò có thể cấp
        $roles = $assignableRoles;
        
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
            
            // Đặc biệt: Nếu user có quyền tạo người dùng thì luôn hiển thị function quan_ly_nguoi_dung
            if ($function->code === 'quan_ly_nguoi_dung' && $currentUser->hasPermission('quan_ly_nguoi_dung', 'create')) {
                $hasAnyPermission = true;
            }
            
            if ($hasAnyPermission) {
                $assignableFunctions->push($function);
            }
        }
        
        $functions = $assignableFunctions;
        $categories = FunctionCategory::active()->ordered()->get();
        
        // Lấy dữ liệu cho role-based system dựa trên cấp độ của user hiện tại
        $currentUser = $this->userService->getCurrentUser();
        $provinces = collect();
        $wards = collect();
        $schools = collect();
        
        // Giới hạn dữ liệu dựa trên cấp độ của user hiện tại
        if ($currentUser->role_id == 1) {
            // Superadmin: có thể tạo user cho tất cả địa giới
            $provinces = \App\Models\GeoUnit::where('type_level', 1)
                ->where('scheme', 'new')
                ->orderBy('name')->get();
            $wards = \App\Models\GeoUnit::where('type_level', 3)
                ->orderBy('name')->get();
            $schools = \App\Models\School::orderBy('name')->get();
        } elseif ($currentUser->role_level === 'province') {
            // User cấp tỉnh: có thể tạo user cấp tỉnh (tỉnh của mình) hoặc cấp trường (trong tỉnh)
            $provinces = \App\Models\GeoUnit::where('type_level', 1)
                ->where('id', $currentUser->province_id)
                ->where('scheme', 'new')
                ->orderBy('name')->get();
            // Chỉ hiển thị các xã trong tỉnh của user
            $wards = \App\Models\GeoUnit::where('type_level', 3)
                ->where('parent_id', $currentUser->province_id)
                ->orderBy('name')->get();
            // Chỉ hiển thị các trường trong tỉnh của user
            $schools = \App\Models\School::where('province_code', $currentUser->province->code ?? null)
                ->orderBy('name')->get();
        } elseif ($currentUser->role_level === 'ward') {
            // User cấp xã: chỉ có thể tạo user cấp xã (xã của mình) hoặc cấp trường (trong xã)
            $provinces = \App\Models\GeoUnit::where('type_level', 1)
                ->where('id', $currentUser->province_id)
                ->where('scheme', 'new')
                ->orderBy('name')->get();
            // Chỉ hiển thị xã của user
            $wards = \App\Models\GeoUnit::where('type_level', 3)
                ->where('id', $currentUser->ward_id)
                ->orderBy('name')->get();
            // Chỉ hiển thị các trường trong xã của user
            $schools = \App\Models\School::where('ward_code_new', $currentUser->ward->code ?? null)
                ->orderBy('name')->get();
        } elseif ($currentUser->role_level === 'school') {
            // User cấp trường: chỉ có thể tạo user cấp trường (trong trường của mình)
            $provinces = \App\Models\GeoUnit::where('type_level', 1)
                ->where('id', $currentUser->province_id)
                ->where('scheme', 'new')
                ->orderBy('name')->get();
            $wards = \App\Models\GeoUnit::where('type_level', 3)
                ->where('id', $currentUser->ward_id)
                ->orderBy('name')->get();
            // Chỉ hiển thị trường của user
            $schools = \App\Models\School::where('id', $currentUser->school_id)
                ->orderBy('name')->get();
        }
        
        return view('front.user-management.create', compact('roles', 'assignableRoles', 'functions', 'categories', 'provinces', 'wards', 'schools', 'currentUserPermissions'));
    }

    /**
     * Lưu người dùng mới
     */
    public function store(Request $request): RedirectResponse
    {
        // Kiểm tra quyền tạo người dùng
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_nguoi_dung', 'create')) {
            abort(403, 'Bạn không có quyền tạo người dùng mới.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'password' => 'required|string|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'role_id' => ['required', 'exists:user_roles,id', new NotSuperadminRole()],
            'role_level' => 'required|in:province,ward,school',
            'province_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'school_id' => 'nullable|integer|exists:schools,id',
            'assignable_roles' => 'nullable|array',
            'assignable_roles.*' => 'exists:user_roles,id',
        ], [
            'email.regex' => 'Chỉ chấp nhận địa chỉ Gmail (@gmail.com)',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $currentUser = $this->userService->getCurrentUser();
            
            // Kiểm tra quyền cấp quyền - sử dụng quyền quan_ly_nguoi_dung
            if (!$currentUser->hasPermission('quan_ly_nguoi_dung', 'create')) {
                return redirect()->back()
                    ->withErrors(['role_id' => 'Bạn không có quyền cấp quyền này.']);
            }

            // Kiểm tra quyền tạo tài khoản trong phạm vi được phép
            if ($currentUser->role_level === 'ward') {
                // User cấp xã chỉ có thể tạo tài khoản trong xã của mình
                if ($request->ward_id && $request->ward_id != $currentUser->ward_id) {
                    return redirect()->back()
                        ->withErrors(['ward_id' => 'Bạn chỉ có thể tạo tài khoản trong xã của mình.'])
                        ->withInput();
                }
                // Kiểm tra trường học phải thuộc xã của user
                if ($request->school_id) {
                    $school = \App\Models\School::find($request->school_id);
                    if ($school && $school->ward_code_new != $currentUser->ward->code) {
                        return redirect()->back()
                            ->withErrors(['school_id' => 'Bạn chỉ có thể tạo tài khoản trong các trường thuộc xã của mình.'])
                            ->withInput();
                    }
                }
            } elseif ($currentUser->role_level === 'school') {
                // User cấp trường chỉ có thể tạo tài khoản trong trường của mình
                if ($request->school_id && $request->school_id != $currentUser->school_id) {
                    return redirect()->back()
                        ->withErrors(['school_id' => 'Bạn chỉ có thể tạo tài khoản trong trường của mình.'])
                        ->withInput();
                }
            } elseif ($currentUser->role_level === 'province') {
                // User cấp tỉnh chỉ có thể tạo tài khoản trong tỉnh của mình
                if ($request->province_id && $request->province_id != $currentUser->province_id) {
                    return redirect()->back()
                        ->withErrors(['province_id' => 'Bạn chỉ có thể tạo tài khoản trong tỉnh của mình.'])
                        ->withInput();
                }
                // Kiểm tra xã phải thuộc tỉnh của user
                if ($request->ward_id) {
                    $ward = \App\Models\GeoUnit::find($request->ward_id);
                    if ($ward && $ward->parent_id != $currentUser->province_id) {
                        return redirect()->back()
                            ->withErrors(['ward_id' => 'Bạn chỉ có thể tạo tài khoản trong các xã thuộc tỉnh của mình.'])
                            ->withInput();
                    }
                }
                // Kiểm tra trường phải thuộc tỉnh của user
                if ($request->school_id) {
                    $school = \App\Models\School::find($request->school_id);
                    if ($school && $school->province_code != $currentUser->province->code) {
                        return redirect()->back()
                            ->withErrors(['school_id' => 'Bạn chỉ có thể tạo tài khoản trong các trường thuộc tỉnh của mình.'])
                            ->withInput();
                    }
                }
            }

            // Tự động lấy ward_id và province_id từ school_id hoặc ward_id
            $provinceId = $request->province_id;
            $wardId = $request->ward_id;
            $schoolId = $request->school_id;
            
            if ($request->school_id && $request->role_level === 'school') {
                $school = \App\Models\School::find($request->school_id);
                if ($school) {
                    $geoInfo = $school->getGeoInfo();
                    $provinceId = $geoInfo['province_id'];
                    $wardId = $geoInfo['ward_id'];
                }
            } elseif ($request->ward_id && $request->role_level === 'ward') {
                $ward = \App\Models\GeoUnit::find($request->ward_id);
                if ($ward) {
                    $provinceId = $ward->getProvinceId();
                }
            }

            // Tạo user mới với phân quyền địa bàn
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // Không hash ở đây, UserService sẽ hash
                'phone_number' => $request->phone_number,
                'role_id' => $request->role_id,
                'role_level' => $request->role_level,
                'province_id' => $provinceId,
                'ward_id' => $wardId,
                'school_id' => $schoolId,
                'is_active' => true,
            ];
            
            $newUser = $this->userService->createUser($userData);

            // Gán permissions nếu có
            if ($request->has('permissions')) {
                $permissions = [];
                if (is_string($request->permissions)) {
                    $permissions = json_decode($request->permissions, true) ?? [];
                } elseif (is_array($request->permissions)) {
                    $permissions = $request->permissions;
                }
                
                // Normalize permissions
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
                
                // Gán permissions
                $this->permissionService->assignUserPermissions($newUser->id, $normalizedPermissions);
            }

            // Lưu assignable roles nếu có
            if ($request->has('assignable_roles') && is_array($request->assignable_roles)) {
                $newUser->assignable_roles = json_encode($request->assignable_roles);
                $newUser->save();
            }

            return redirect()->route('front.user-management.index')
                ->with('success', 'Tạo người dùng và phân quyền thành công! Người dùng có thể đăng nhập ngay bằng tài khoản Google với địa chỉ: ' . $request->email);

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Hiển thị form chỉnh sửa người dùng
     */
    public function edit(int $id): View
    {
        // Kiểm tra quyền chỉnh sửa người dùng
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_nguoi_dung', 'edit')) {
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng.');
        }

        $userToEdit = User::findOrFail($id);
        
        // Kiểm tra quyền quản lý dựa trên cấp độ địa lý
        if (!$user->canManageUser($userToEdit)) {
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng này. Chỉ có thể quản lý người dùng cùng cấp hoặc cấp thấp hơn trong phạm vi của bạn.');
        }
        
        $allRoles = UserRole::getSelectableRoles();
        
        // Lấy functions mà user hiện tại có quyền
        $currentUser = $this->userService->getCurrentUser();
        $currentUserPermissions = $currentUser->getAllPermissions();
        
        // Lọc roles mà user hiện tại có thể cấp
        $roles = collect();
        foreach ($allRoles as $role) {
            if ($currentUser->canGrantRole($role->name)) {
                $roles->push($role);
            }
        }
        
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
            
            // Đặc biệt: Nếu user có quyền chỉnh sửa người dùng thì luôn hiển thị function quan_ly_nguoi_dung
            if ($function->code === 'quan_ly_nguoi_dung' && $currentUser->hasPermission('quan_ly_nguoi_dung', 'edit')) {
                $hasAnyPermission = true;
            }
            
            if ($hasAnyPermission) {
                $assignableFunctions->push($function);
            }
        }
        
        $functions = $assignableFunctions;
        $categories = FunctionCategory::active()->ordered()->get();
        
        // Lấy dữ liệu cho role-based system - sử dụng logic từ admin
        $provinces = \App\Models\GeoUnit::where('type_level', 1)->where('scheme', 'new')->orderBy('name')->get();
        $wards = collect();
        $schools = collect();
        
        // Superadmin có thể xem tất cả địa giới
        if ($currentUser->role_id == 1) {
            $wards = \App\Models\GeoUnit::where('type_level', 3)->orderBy('name')->get();
            $schools = \App\Models\School::orderBy('name')->get();
        } else {
            // Nếu user có province_id, load wards
            if ($userToEdit->province_id) {
                $wards = \App\Models\GeoUnit::where('parent_id', $userToEdit->province_id)
                    ->where('type_level', 3)
                    ->orderBy('name')
                    ->get();
            }
            
            // Nếu user có ward_id, load schools
            if ($userToEdit->ward_id) {
                $ward = \App\Models\GeoUnit::find($userToEdit->ward_id);
                if ($ward) {
                    $schools = \App\Models\School::where('ward_code_new', $ward->code)
                        ->orderBy('name')
                        ->get();
                }
            }
        }
        
        return view('front.user-management.edit', compact('userToEdit', 'roles', 'functions', 'categories', 'provinces', 'wards', 'schools', 'currentUserPermissions'));
    }

    /**
     * Cập nhật người dùng
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        // Kiểm tra quyền chỉnh sửa người dùng
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_nguoi_dung', 'edit')) {
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng.');
        }

        $userToUpdate = User::findOrFail($id);
        $currentUser = $this->userService->getCurrentUser();
        
        // Kiểm tra quyền quản lý dựa trên cấp độ địa lý
        if (!$user->canManageUser($userToUpdate)) {
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng này. Chỉ có thể quản lý người dùng cùng cấp hoặc cấp thấp hơn trong phạm vi của bạn.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id . '|regex:/^[a-zA-Z0-9._%+-]+@gmail\.com$/',
            'password' => 'nullable|string|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'role_id' => ['required', 'exists:user_roles,id', new NotSuperadminRole()],
            'role_level' => 'required|string|in:province,ward,school',
            'province_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'school_id' => 'nullable|integer|exists:schools,id',
            'assignable_roles' => 'nullable|array',
            'assignable_roles.*' => 'exists:user_roles,id',
        ], [
            'email.regex' => 'Chỉ chấp nhận địa chỉ Gmail (@gmail.com)',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // Kiểm tra quyền cấp quyền - sử dụng quyền quan_ly_nguoi_dung
            if (!$currentUser->hasPermission('quan_ly_nguoi_dung', 'edit')) {
                return redirect()->back()
                    ->withErrors(['role_id' => 'Bạn không có quyền cấp quyền này.']);
            }

            // Xử lý dữ liệu địa bàn
            $provinceId = $request->province_id;
            $wardId = $request->ward_id;
            $schoolId = $request->school_id;
            
            if ($request->school_id && $request->role_level === 'school') {
                $school = \App\Models\School::find($request->school_id);
                if ($school) {
                    $geoInfo = $school->getGeoInfo();
                    $provinceId = $geoInfo['province_id'];
                    $wardId = $geoInfo['ward_id'];
                }
            } elseif ($request->ward_id && $request->role_level === 'ward') {
                $ward = \App\Models\GeoUnit::find($request->ward_id);
                if ($ward) {
                    $provinceId = $ward->getProvinceId();
                }
            }
            
            // Chuẩn bị dữ liệu cập nhật
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'role_id' => $request->role_id,
                'role_level' => $request->role_level,
                'province_id' => $provinceId,
                'ward_id' => $wardId,
                'school_id' => $schoolId,
            ];
            
            // Chỉ cập nhật mật khẩu nếu có
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }
            
            // Cập nhật thông tin user
            $userToUpdate->update($updateData);

            // Cập nhật permissions nếu có
            if ($request->has('permissions')) {
                $permissions = [];
                if (is_string($request->permissions)) {
                    $permissions = json_decode($request->permissions, true) ?? [];
                } elseif (is_array($request->permissions)) {
                    $permissions = $request->permissions;
                }
                
                // Normalize permissions
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
                
                // Cập nhật permissions
                $this->permissionService->assignUserPermissions($userToUpdate->id, $normalizedPermissions);
            }

            // Cập nhật assignable roles nếu có
            if ($request->has('assignable_roles')) {
                $userToUpdate->assignable_roles = $request->assignable_roles;
                $userToUpdate->save();
            }

            return redirect()->route('front.user-management.index')
                ->with('success', 'Cập nhật thông tin người dùng và phân quyền thành công!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Xóa người dùng
     */
    public function destroy(int $id): RedirectResponse
    {
        // Kiểm tra quyền xóa người dùng
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_nguoi_dung', 'delete')) {
            abort(403, 'Bạn không có quyền xóa người dùng.');
        }

        $userToDelete = User::findOrFail($id);

        // Kiểm tra quyền quản lý dựa trên cấp độ địa lý
        if (!$user->canManageUser($userToDelete)) {
            return redirect()->back()->withErrors(['error' => 'Bạn không có quyền xóa người dùng này. Chỉ có thể quản lý người dùng cùng cấp hoặc cấp thấp hơn trong phạm vi của bạn.']);
        }

        try {
            $userToDelete->delete();
            return redirect()->route('front.user-management.index')
                ->with('success', 'Xóa người dùng thành công!');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }

    /**
     * Hiển thị trang phân quyền cho user cụ thể
     * @phpstan-ignore-next-line
     */
    public function permissions(int $id): View
    {
        $user = Auth::user();
        if (!$user) {
            abort(403, 'Bạn cần đăng nhập để truy cập.');
        }

        $userToEdit = User::findOrFail($id);
        
        // Bỏ kiểm tra hạn chế xem phân quyền - cho phép xem tất cả người dùng
        
        $currentUser = Auth::user();
        
        // Lấy tất cả permissions của user được phân quyền
        /** @var array $permissions */
        $permissions = $userToEdit->getAllPermissions(); // @phpstan-ignore-line
        
        // Lấy permissions của user hiện tại để chỉ hiển thị những quyền họ có
        /** @var array $currentUserPermissions */
        $currentUserPermissions = $currentUser->getAllPermissions(); // @phpstan-ignore-line
        
        // Trong trang phân quyền, hiển thị tất cả functions để user có thể phân quyền
        // Nhưng chỉ hiển thị những action mà current user có quyền
        $functions = SystemFunction::active()->get();
        $categories = FunctionCategory::active()->ordered()->get();
        
        return view('front.user-management.permissions', compact('userToEdit', 'permissions', 'functions', 'categories', 'currentUserPermissions'));
    }

    /**
     * Lưu phân quyền cho user
     * @phpstan-ignore-next-line
     */
    public function savePermissions(Request $request, int $id): RedirectResponse
    {
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_nguoi_dung', 'edit')) { // @phpstan-ignore-line
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng.');
        }

        try {
            $userToEdit = User::findOrFail($id);
            
            // Kiểm tra: Không cho phép lưu phân quyền người dùng có cùng quyền quan_ly_nguoi_dung (trừ quyền view)
            if ($userToEdit->hasPermission('quan_ly_nguoi_dung', 'create') || 
                $userToEdit->hasPermission('quan_ly_nguoi_dung', 'edit') || 
                $userToEdit->hasPermission('quan_ly_nguoi_dung', 'delete')) {
                return redirect()->back()->withErrors(['error' => 'Bạn không thể phân quyền cho người dùng có quyền quản lý người dùng.']);
            }
            
            $currentUser = Auth::user();
            
            // Lấy permissions của user hiện tại để kiểm tra quyền phân quyền
            /** @var array $currentUserPermissions */
            $currentUserPermissions = $currentUser->getAllPermissions(); // @phpstan-ignore-line
            
            // Lấy tất cả functions
            $functions = SystemFunction::active()->get();
            
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
            
            $this->permissionService->assignUserPermissions($userToEdit->id, $normalizedPermissions);
            
            return redirect()->back()->with('success', 'Phân quyền chức năng thành công!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
    }
}
