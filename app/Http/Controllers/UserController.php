<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use App\Rules\NotSuperadminRole;

class UserController extends Controller
{
    protected UserService $userService;
    protected PermissionService $permissionService;

    public function __construct(UserService $userService, PermissionService $permissionService)
    {
        $this->userService = $userService;
        $this->permissionService = $permissionService;
    }

    /**
     * Hiển thị form đăng nhập
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Xử lý đăng nhập thông thường (email/password)
     */
    public function loginSubmit(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if ($this->userService->login($request->email, $request->password, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended(route('front.home', absolute: false));
        }

        throw ValidationException::withMessages([
            'email' => 'Thông tin đăng nhập không chính xác.',
        ]);
    }

    /**
     * Xử lý đăng nhập admin
     */
    public function loginAdmin(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if ($this->userService->loginAdmin($request->email, $request->password, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        throw ValidationException::withMessages([
            'email' => 'Thông tin đăng nhập admin không chính xác.',
        ]);
    }

    /**
     * Xử lý đăng nhập (legacy method)
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if ($this->userService->login($request->email, $request->password, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.dashboard', absolute: false));
        }

        throw ValidationException::withMessages([
            'email' => 'Thông tin đăng nhập không chính xác.',
        ]);
    }

    /**
     * Xử lý đăng xuất
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->userService->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Hiển thị thông tin user hiện tại
     */
    public function profile(): View
    {
        $user = $this->userService->getCurrentUser();
        
        return view('user.profile', compact('user'));
    }

    /**
     * Lấy thông tin user theo ID (API)
     */
    public function show(int $id)
    {
        $user = $this->userService->findById($id);
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    /**
     * Hiển thị chi tiết user (admin only)
     */
    public function showUser($user): View
    {
        $user = $this->userService->findById((int) $user);
        
        if (!$user) {
            abort(404, 'Người dùng không tồn tại.');
        }

        // Lấy thông tin phân quyền
        $permissions = $this->permissionService->getUserPermissions($user->id);
        
        return view('admin.users.show', compact('user', 'permissions'));
    }

    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $user = $this->userService->handleGoogleCallback();
            $request->session()->regenerate();

            return redirect()->intended(route('front.home', absolute: false));
        } catch (ValidationException $e) {
            return redirect()->route('auth.google.login')
                ->withErrors($e->errors());
        }
    }

    /**
     * Hiển thị form đăng nhập Google
     */
    public function showGoogleLoginForm(): View
    {
        return view('auth.google-login');
    }

    /**
     * Hiển thị form đăng nhập admin
     */
    public function showAdminLoginForm(): View
    {
        return view('auth.admin-login');
    }

    /**
     * Hiển thị danh sách user (admin only)
     */
    public function index(Request $request): View
    {
        // Kiểm tra quyền quản lý người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2 && $user->role_id != 3)) {
            abort(403, 'Bạn không có quyền xem danh sách người dùng.');
        }
        
        // Lấy danh sách user theo quy tắc 3 cấp độ địa bàn
        $users = $this->permissionService->getManageableUsers($this->userService->getCurrentUser());
        
        // Áp dụng bộ lọc tìm kiếm
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $users = $users->filter(function($user) use ($searchTerm) {
                return stripos($user->name, $searchTerm) !== false || 
                       stripos($user->email, $searchTerm) !== false;
            });
        }
        
        // Áp dụng bộ lọc vai trò
        if ($request->filled('role')) {
            $users = $users->filter(function($user) use ($request) {
                return $user->userRole && $user->userRole->name === $request->role;
            });
        }
        
        // Áp dụng bộ lọc danh mục chức năng
        if ($request->filled('category')) {
            $category = $request->category;
            $users = $users->filter(function($user) use ($category) {
                $permissions = $user->getAllPermissions();
                return collect($permissions)->keys()->contains($category);
            });
        }
        
        // Lấy thông tin về phạm vi vai trò
        $currentUser = $this->userService->getCurrentUser();
        $roleScope = null;
        
        if ($currentUser && !$currentUser->isAdmin()) {
            $roleInfo = $currentUser->getRoleLevelAndScope();
            $roleScope = "Phạm vi: " . $roleInfo['scope_display'];
        }
        
        // Lấy dữ liệu cần thiết cho phân quyền
        $roles = \App\Models\UserRole::getSelectableRoles();
        $functions = \App\Models\SystemFunction::active()->get();
        $categories = $this->permissionService->getAllCategories();
        
        return view('admin.users.index', compact('users', 'roleScope', 'roles', 'functions', 'categories'));
    }

    /**
     * Hiển thị form tạo user mới (admin only)
     */
    public function create(): View
    {
        // Kiểm tra quyền tạo người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền tạo người dùng mới.');
        }
        
        $allRoles = \App\Models\UserRole::getSelectableRoles();
        
        // Lọc roles mà user hiện tại có thể cấp
        $assignableRoles = collect();
        foreach ($allRoles as $role) {
            if ($user->canGrantRole($role->name)) {
                $assignableRoles->push($role);
            }
        }
        
        $roles = $assignableRoles;
        $functions = \App\Models\SystemFunction::active()->get();
        $categories = \App\Models\FunctionCategory::active()->ordered()->get();
        
        // Lấy dữ liệu cho role-based system
        $provinces = $this->permissionService->getAssignableProvinces($user);
        $wards = $this->permissionService->getAssignableWards($user);
        $schools = $this->permissionService->getAssignableSchools($user);
        
        // Debug: Log dữ liệu để kiểm tra
        \Illuminate\Support\Facades\Log::info('Create user form data:', [
            'user_id' => $user->id,
            'user_role' => $user->role_id,
            'is_admin' => $user->role_id == 1,
            'provinces_count' => $provinces->count(),
            'wards_count' => $wards->count(),
            'schools_count' => $schools->count(),
        ]);
        
        return view('admin.users.create', compact('roles', 'functions', 'categories', 'provinces', 'wards', 'schools'));
    }

    /**
     * Tạo user mới (admin only)
     */
    public function store(Request $request): RedirectResponse
    {
        // dd($request->all());
        // Kiểm tra quyền tạo người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền tạo người dùng mới.');
        }
        
        // Debug: Log dữ liệu form
        Log::info('Form data received:', [
            'role_level' => $request->role_level,
            'province_id' => $request->province_id,
            'ward_id' => $request->ward_id,
            'school_id' => $request->school_id,
            'all_data' => $request->all()
        ]);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'role' => 'required|string|exists:user_roles,name',
            'role_level' => 'required|in:province,ward,school',
            'province_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'school_id' => 'nullable|integer|exists:schools,id',
            'assignable_roles' => 'nullable|array',
            'assignable_roles.*' => 'exists:user_roles,id',
            // Giữ lại cho tương thích ngược
            'geo_scheme' => 'nullable|in:old,new',
            'geo_province' => 'nullable|integer|exists:geo_units,id',
            'geo_commune' => 'nullable|integer|exists:geo_units,id',
            'geo_note' => 'nullable|string|max:500',
        ]);

        $currentUser = $this->userService->getCurrentUser();
        
        // Kiểm tra quyền cấp quyền
        if (!$currentUser->canGrantRole($request->role)) {
            return redirect()->back()
                ->withErrors(['role' => 'Bạn không có quyền cấp quyền này.']);
        }

        try {
            // Tự động lấy ward_id và province_id từ school_id hoặc ward_id
            $provinceId = $request->province_id;
            $wardId = $request->ward_id;
            
            if ($request->school_id && $request->role_level === 'school') {
                $school = \App\Models\School::find($request->school_id);
                if ($school) {
                    $geoInfo = $school->getGeoInfo();
                    $provinceId = $geoInfo['province_id'];
                    $wardId = $geoInfo['ward_id'];
                    
                    Log::info('Auto-mapped geo info from school', [
                        'school_id' => $request->school_id,
                        'ward_code_new' => $school->ward_code_new,
                        'mapped_ward_id' => $wardId,
                        'mapped_province_id' => $provinceId
                    ]);
                }
            } elseif ($request->ward_id && $request->role_level === 'ward') {
                $ward = \App\Models\GeoUnit::find($request->ward_id);
                if ($ward) {
                    $provinceId = $ward->getProvinceId();
                    
                    Log::info('Auto-mapped geo info from ward', [
                        'ward_id' => $request->ward_id,
                        'ward_code' => $ward->code,
                        'mapped_province_id' => $provinceId
                    ]);
                }
            }
            
            // Tạo user mới với thông tin địa lý đã được map
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // Không hash ở đây, UserService sẽ hash
                'phone_number' => $request->phone_number,
                'role_level' => $request->role_level,
                'province_id' => $provinceId,
                'ward_id' => $wardId,
                'school_id' => $request->school_id,
            ];
            
            $user = $this->userService->createUser($userData);

            // Gán role cho user
            $role = \App\Models\UserRole::where('name', $request->role)->first();
            if ($role) {
                $user->update(['role_id' => $role->id]);
            }

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
                
                // Gán permissions sử dụng cùng method như update()
                $this->permissionService->assignUserPermissions($user->id, $normalizedPermissions);
            }

            // Lưu assignable roles nếu có
            if ($request->has('assignable_roles') && is_array($request->assignable_roles)) {
                $user->assignable_roles = $request->assignable_roles;
                $user->save();
            }

            // Log thông tin user đã tạo
            Log::info('User created with role level', [
                'user_id' => $user->id,
                'role_level' => $request->role_level,
                'province_id' => $request->province_id,
                'ward_id' => $request->ward_id,
                'school_id' => $request->school_id,
                'user_data' => $user->fresh(['province', 'ward', 'school'])->toArray()
            ]);

            
            return redirect()->route('admin.users.index')
                ->with('success', 'Tạo người dùng và phân quyền thành công.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }

    /**
     * Hiển thị form chỉnh sửa user (admin only)
     */
    public function edit(int $id): View
    {
        // Kiểm tra quyền chỉnh sửa người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng.');
        }
        
        $userToEdit = $this->userService->findById($id);
        if (!$userToEdit) {
            abort(404, 'Người dùng không tồn tại.');
        }
        
        $allRoles = \App\Models\UserRole::getSelectableRoles();
        
        // Lọc roles mà user hiện tại có thể cấp
        $assignableRoles = collect();
        foreach ($allRoles as $role) {
            if ($user->canGrantRole($role->name)) {
                $assignableRoles->push($role);
            }
        }
        
        $roles = $assignableRoles;
        $functions = \App\Models\SystemFunction::active()->get();
        $categories = \App\Models\FunctionCategory::active()->ordered()->get();
        $provinces = \App\Models\GeoUnit::where('type_level', 1)->where('scheme', 'new')->orderBy('name')->get();
        
        // Lấy dữ liệu cho role-based system - sử dụng logic từ user-management
        $wards = collect();
        $schools = collect();
        
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
        
        // Đảm bảo school hiện tại của user có trong danh sách (nếu có school_id)
        if ($userToEdit->school_id) {
            $currentSchool = \App\Models\School::find($userToEdit->school_id);
            if ($currentSchool) {
                if ($schools->isNotEmpty() && !$schools->contains('id', $userToEdit->school_id)) {
                    // Thêm school hiện tại vào đầu danh sách nếu chưa có
                    $schools->prepend($currentSchool);
                } elseif ($schools->isEmpty()) {
                    // Nếu không có schools từ ward, chỉ load school hiện tại
                    $schools = collect([$currentSchool]);
                }
            }
        }
        
        return view('admin.users.edit', compact('userToEdit', 'roles', 'functions', 'categories', 'provinces', 'wards', 'schools'));
    }

    /**
     * Cập nhật user (admin only)
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        // Kiểm tra quyền chỉnh sửa người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng.');
        }
        
        $user = $this->userService->findById($id);
        if (!$user) {
            abort(404, 'Người dùng không tồn tại.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'role' => 'required|string|exists:user_roles,name',
            'role_level' => 'required|string|in:province,ward,school',
            'province_id' => 'nullable|integer|exists:geo_units,id',
            'ward_id' => 'nullable|integer|exists:geo_units,id',
            'school_id' => 'nullable|integer|exists:schools,id',
            'assignable_roles' => 'nullable|array',
            'assignable_roles.*' => 'exists:user_roles,id',
            'is_active' => 'boolean',
        ]);

        $currentUser = $this->userService->getCurrentUser();
        
        // Kiểm tra quyền cấp quyền
        if (!$currentUser->canGrantRole($request->role)) {
            return redirect()->back()
                ->withErrors(['role' => 'Bạn không có quyền cấp quyền này.']);
        }

        try {
            // Tự động lấy ward_id và province_id từ school_id hoặc ward_id
            $provinceId = $request->province_id;
            $wardId = $request->ward_id;
            
            if ($request->school_id && $request->role_level === 'school') {
                $school = \App\Models\School::find($request->school_id);
                if ($school) {
                    $geoInfo = $school->getGeoInfo();
                    $provinceId = $geoInfo['province_id'];
                    $wardId = $geoInfo['ward_id'];
                    
                    Log::info('Auto-mapped geo info from school (update)', [
                        'school_id' => $request->school_id,
                        'ward_code_new' => $school->ward_code_new,
                        'mapped_ward_id' => $wardId,
                        'mapped_province_id' => $provinceId
                    ]);
                }
            } elseif ($request->ward_id && $request->role_level === 'ward') {
                $ward = \App\Models\GeoUnit::find($request->ward_id);
                if ($ward) {
                    $provinceId = $ward->getProvinceId();
                    
                    Log::info('Auto-mapped geo info from ward (update)', [
                        'ward_id' => $request->ward_id,
                        'ward_code' => $ward->code,
                        'mapped_province_id' => $provinceId
                    ]);
                }
            }
            
            // Chuẩn bị dữ liệu cập nhật
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number,
                'is_active' => $request->boolean('is_active'),
                'role_level' => $request->role_level,
                'province_id' => $provinceId,
                'ward_id' => $wardId,
                'school_id' => $request->school_id,
            ];
            
            // Chỉ cập nhật mật khẩu nếu có
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }
            
            // Cập nhật thông tin cơ bản và role level với thông tin địa lý đã được map
            $user->update($updateData);

            // Cập nhật role
            $role = \App\Models\UserRole::where('name', $request->role)->first();
            if ($role) {
                $user->update(['role_id' => $role->id]);
            }

            // Cập nhật assignable roles nếu có
            if ($request->has('assignable_roles')) {
                $user->assignable_roles = $request->assignable_roles;
                $user->save();
            }

            // Log thông tin user đã cập nhật
            Log::info('User updated with role level', [
                'user_id' => $user->id,
                'role_level' => $request->role_level,
                'province_id' => $request->province_id,
                'ward_id' => $request->ward_id,
                'school_id' => $request->school_id,
                'user_data' => $user->fresh(['province', 'ward', 'school'])->toArray()
            ]);

            // Cập nhật phân quyền chức năng nếu có
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
                
                $this->permissionService->assignUserPermissions($user->id, $normalizedPermissions);
            }
            
            return redirect()->route('admin.permissions.index')
                ->with('success', 'Cập nhật người dùng thành công.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }

    /**
     * Cập nhật quyền user (admin only)
     */
    public function updateRole(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'role' => 'required|string|exists:user_roles,name',
        ]);

        $user = $this->userService->findById($id);
        $currentUser = $this->userService->getCurrentUser();

        if (!$user) {
            return redirect()->back()->withErrors(['user' => 'Người dùng không tồn tại.']);
        }

        if (!$currentUser->canGrantRole($request->role)) {
            return redirect()->back()->withErrors(['role' => 'Bạn không có quyền cấp quyền này.']);
        }

        try {
            $role = \App\Models\UserRole::where('name', $request->role)->first();
            if ($role) {
                $user->update(['role_id' => $role->id]);
                return redirect()->back()->with('success', 'Cập nhật quyền thành công.');
            }
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }

        return redirect()->back()->withErrors(['role' => 'Có lỗi xảy ra khi cập nhật quyền.']);
    }

    /**
     * Xóa user (admin only)
     */
    public function destroy(int $id): RedirectResponse
    {
        // Kiểm tra quyền xóa người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền xóa người dùng.');
        }
        
        $user = $this->userService->findById($id);
        $currentUser = $this->userService->getCurrentUser();

        if (!$user) {
            return redirect()->back()->withErrors(['user' => 'Người dùng không tồn tại.']);
        }

        // Không cho phép xóa chính mình
        if ($user->id === $currentUser->id) {
            return redirect()->back()->withErrors(['user' => 'Không thể xóa tài khoản của chính mình.']);
        }

        $user->delete();

        return redirect()->back()->with('success', 'Xóa người dùng thành công.');
    }

    /**
     * Xóa nhiều user cùng lúc (admin only)
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        // Kiểm tra quyền xóa người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền xóa người dùng.');
        }

        $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $userIds = $request->user_ids;
        $currentUser = $this->userService->getCurrentUser();
        $deletedCount = 0;
        $errors = [];

        foreach ($userIds as $userId) {
            $user = $this->userService->findById((int) $userId);
            
            if (!$user) {
                $errors[] = "Người dùng ID {$userId} không tồn tại.";
                continue;
            }

            // Không cho phép xóa chính mình
            if ($user->id === $currentUser->id) {
                $errors[] = "Không thể xóa tài khoản của chính mình.";
                continue;
            }

            // Không cho phép xóa superadmin
            if ($user->role_id == 1) {
                $errors[] = "Không thể xóa tài khoản superadmin.";
                continue;
            }

            try {
                $user->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = "Lỗi khi xóa người dùng {$user->name}: " . $e->getMessage();
            }
        }

        $message = "Đã xóa thành công {$deletedCount} người dùng.";
        if (!empty($errors)) {
            $message .= " Một số lỗi: " . implode(', ', $errors);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Cập nhật nhanh vai trò người dùng (admin only)
     */
    public function quickUpdateRole(Request $request, int $id): RedirectResponse
    {
        // Kiểm tra quyền chỉnh sửa người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng.');
        }

        $request->validate([
            'role_id' => 'required|integer|exists:user_roles,id',
        ]);

        $targetUser = $this->userService->findById($id);
        if (!$targetUser) {
            return redirect()->back()->withErrors(['user' => 'Người dùng không tồn tại.']);
        }

        // Không cho phép sửa vai trò superadmin
        if ($targetUser->role_id == 1) {
            return redirect()->back()->withErrors(['role' => 'Không thể thay đổi vai trò của superadmin.']);
        }

        $currentUser = $this->userService->getCurrentUser();
        $newRole = \App\Models\UserRole::find($request->role_id);
        
        if (!$newRole) {
            return redirect()->back()->withErrors(['role' => 'Vai trò không tồn tại.']);
        }

        // Kiểm tra quyền cấp quyền
        if (!$currentUser->canGrantRole($newRole->name)) {
            return redirect()->back()->withErrors(['role' => 'Bạn không có quyền cấp quyền này.']);
        }

        try {
            $targetUser->update(['role_id' => $request->role_id]);
            return redirect()->back()->with('success', 'Cập nhật vai trò thành công.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Có lỗi xảy ra: ' . $e->getMessage()]);
        }
    }

    // ========================================
    // FRONT-END USER MANAGEMENT METHODS
    // ========================================

    /**
     * Hiển thị trang quản lý người dùng (Front-end)
     */
    public function frontIndex(Request $request): View
    {
        // Kiểm tra quyền quản lý người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2 && $user->role_id != 3)) {
            abort(403, 'Bạn không có quyền xem danh sách người dùng.');
        }
        
        // Sử dụng logic phân quyền 3 cấp độ địa bàn
        $users = $this->permissionService->getManageableUsers($this->userService->getCurrentUser());
        
        // Áp dụng bộ lọc tìm kiếm
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $users = $users->filter(function($user) use ($searchTerm) {
                return stripos($user->name, $searchTerm) !== false || 
                       stripos($user->email, $searchTerm) !== false;
            });
        }
        
        // Áp dụng bộ lọc vai trò
        if ($request->filled('role')) {
            $users = $users->filter(function($user) use ($request) {
                return $user->userRole && $user->userRole->name === $request->role;
            });
        }
        
        // Áp dụng bộ lọc danh mục chức năng
        if ($request->filled('category')) {
            $category = $request->category;
            $users = $users->filter(function($user) use ($category) {
                $permissions = $user->getAllPermissions();
                return collect($permissions)->keys()->contains($category);
            });
        }
        
        // Lấy thông tin về phạm vi vai trò
        $currentUser = $this->userService->getCurrentUser();
        $geoScope = null;
        
        if ($currentUser && !$currentUser->isAdmin()) {
            $roleInfo = $currentUser->getRoleLevelAndScope();
            $geoScope = "Phạm vi: " . $roleInfo['scope_display'];
        }
        
        // Lấy dữ liệu cần thiết cho phân quyền
        $roles = \App\Models\UserRole::getSelectableRoles();
        $functions = \App\Models\SystemFunction::active()->get();
        $categories = $this->permissionService->getAllCategories();
        
        return view('front.user-management.index', compact('users', 'geoScope', 'roles', 'functions', 'categories'));
    }

    /**
     * Hiển thị form thêm người dùng mới (Front-end)
     */
    public function frontCreate(): View
    {
        // Kiểm tra quyền tạo người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2)) {
            abort(403, 'Bạn không có quyền tạo người dùng mới.');
        }
        
        // Sử dụng logic từ backend
        $roles = \App\Models\UserRole::getSelectableRoles();
        $functions = \App\Models\SystemFunction::active()->get();
        $categories = \App\Models\FunctionCategory::active()->ordered()->get();
        
        return view('admin.users.create', compact('roles', 'functions', 'categories'));
    }

    /**
     * Hiển thị trang xem người dùng (Front-end)
     */
    public function frontView(): View
    {
        // Kiểm tra quyền quản lý người dùng
        $user = Auth::user();
        if (!$user || ($user->role_id != 1 && $user->role_id != 2 && $user->role_id != 3)) {
            abort(403, 'Bạn không có quyền xem danh sách người dùng.');
        }
        
        $users = $this->userService->getUsersByRole();
        return view('front.user-management.index', compact('users'));
    }

    /**
     * Hiển thị form sửa phân quyền địa giới (Front-end)
     */
    public function frontEditPermission(int $userId): View
    {
        // Kiểm tra quyền chỉnh sửa người dùng
        $currentUser = Auth::user();
        if (!$currentUser || ($currentUser->role_id != 1 && $currentUser->role_id != 2)) {
            abort(403, 'Bạn không có quyền chỉnh sửa người dùng.');
        }
        
        $user = $this->userService->findById($userId);
        
        if (!$user) {
            abort(404, 'Người dùng không tồn tại.');
        }

        // Sử dụng logic từ backend
        $roles = \App\Models\UserRole::getSelectableRoles();
        $functions = \App\Models\SystemFunction::active()->get();
        $categories = \App\Models\FunctionCategory::active()->ordered()->get();
        
        return view('admin.users.edit', compact('user', 'roles', 'functions', 'categories'));
    }
}
