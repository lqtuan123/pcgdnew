@extends('layouts.admin')

@section('title', 'Chỉnh sửa người dùng')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Chỉnh sửa người dùng: {{ $userToEdit->name }}</h1>
        <a href="{{ route('admin.users.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left mr-2"></i> Quay lại
        </a>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $userToEdit->id) }}" class="space-y-6">
        @csrf
        @method('PUT')
        
        <!-- Thông tin cơ bản -->
        <article class="card">
            <h2 class="text-lg font-semibold mb-4">Thông tin cơ bản</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tên người dùng *</label>
                    <input type="text" class="w-full rounded-lg border px-3 py-2 @error('name') border-rose-300 @enderror" 
                           name="name" value="{{ old('name', $userToEdit->name) }}" required>
                    @error('name')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                    <input type="email" class="w-full rounded-lg border px-3 py-2 @error('email') border-rose-300 @enderror" 
                           name="email" value="{{ old('email', $userToEdit->email) }}" required>
                    @error('email')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu mới</label>
                    <input type="password" class="w-full rounded-lg border px-3 py-2 @error('password') border-rose-300 @enderror" 
                           name="password" value="{{ old('password') }}">
                    <div class="text-xs text-slate-500 mt-1">Để trống nếu không muốn thay đổi mật khẩu</div>
                    @error('password')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Xác nhận mật khẩu mới</label>
                    <input type="password" class="w-full rounded-lg border px-3 py-2 @error('password_confirmation') border-rose-300 @enderror" 
                           name="password_confirmation" value="{{ old('password_confirmation') }}">
                    @error('password_confirmation')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            <div class="grid md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Số điện thoại</label>
                    <input type="text" class="w-full rounded-lg border px-3 py-2 @error('phone_number') border-rose-300 @enderror" 
                           name="phone_number" value="{{ old('phone_number', $userToEdit->phone_number) }}">
                    @error('phone_number')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Vai trò *</label>
                    <select class="w-full rounded-lg border px-3 py-2 @error('role') border-rose-300 @enderror" 
                            name="role" required onchange="loadRolePermissions()">
                        <option value="">Chọn vai trò</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" 
                                    {{ old('role', $userToEdit->userRole?->name) == $role->name ? 'selected' : '' }}>
                                {{ $role->display_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-4">
                <label class="flex items-center">
                    <input type="checkbox" class="rounded" name="is_active" value="1"
                           {{ old('is_active', $userToEdit->is_active) ? 'checked' : '' }}>
                    <span class="ml-2 text-sm text-slate-700">Tài khoản hoạt động</span>
                </label>
            </div>
        </article>

        <!-- Vai trò có thể cấp -->
        <article class="card">
            <h2 class="text-lg font-semibold mb-4">Vai trò có thể cấp</h2>
            
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    Chọn các vai trò mà tài khoản này có thể sử dụng khi tạo tài khoản và phân quyền cho người khác.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($roles as $role)
                    <div class="flex items-center space-x-3">
                        <input 
                            type="checkbox" 
                            id="assignable_role_{{ $role->id }}" 
                            name="assignable_roles[]" 
                            value="{{ $role->id }}"
                            @if(in_array($role->id, is_array($userToEdit->assignable_roles) ? $userToEdit->assignable_roles : json_decode($userToEdit->assignable_roles ?? '[]', true) ?? [])) checked @endif
                            class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2"
                        >
                        <label for="assignable_role_{{ $role->id }}" class="text-sm font-medium text-gray-900">
                            {{ $role->display_name }}
                        </label>
                    </div>
                @endforeach
            </div>
        </article>

        <!-- Phân quyền Role Level -->
        <article class="card">
            <h2 class="text-lg font-semibold mb-4">Phân quyền theo cấp</h2>
            
            <div class="space-y-4">
                <!-- Role Level -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Cấp độ *</label>
                    <select name="role_level" class="w-full rounded-lg border px-3 py-2 @error('role_level') border-rose-300 @enderror" 
                            required onchange="onRoleLevelChange(this.value)">
                        <option value="">-- Chọn cấp độ --</option>
                        <option value="province" {{ old('role_level', $userToEdit->role_level) === 'province' ? 'selected' : '' }}>Province - Tỉnh/Thành phố</option>
                        <option value="ward" {{ old('role_level', $userToEdit->role_level) === 'ward' ? 'selected' : '' }}>Ward - Xã/Phường</option>
                        <option value="school" {{ old('role_level', $userToEdit->role_level) === 'school' ? 'selected' : '' }}>School - Trường học</option>
                    </select>
                    @error('role_level')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Province selection -->
                <div id="province-section" style="display: none;">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Tỉnh/Thành phố *</label>
                    <select name="province_id" class="w-full rounded-lg border px-3 py-2 @error('province_id') border-rose-300 @enderror">
                        <option value="">-- Chọn tỉnh/thành phố --</option>
                        @foreach($provinces as $province)
                            <option value="{{ $province->id }}" {{ old('province_id', $userToEdit->province_id) == $province->id ? 'selected' : '' }}>
                                {{ $province->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('province_id')
                        <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Ward selection -->
                <div id="ward-section" style="display: none;">
                    <div class="space-y-4">
                        <!-- Province selection for ward -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Tỉnh/Thành phố *</label>
                            <select id="ward-province-select" class="w-full rounded-lg border px-3 py-2 @error('ward_province_id') border-rose-300 @enderror" onchange="loadWardsByProvince(this.value)">
                                <option value="">-- Chọn tỉnh/thành phố --</option>
                                @foreach($provinces as $province)
                                    <option value="{{ $province->id }}" {{ old('ward_province_id', $userToEdit->province_id) == $province->id ? 'selected' : '' }}>
                                        {{ $province->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ward_province_id')
                                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Ward selection -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Xã/Phường *</label>
                            <select id="ward-select" name="ward_id" class="w-full rounded-lg border px-3 py-2 @error('ward_id') border-rose-300 @enderror">
                                <option value="">-- Chọn xã/phường --</option>
                                @foreach($wards as $ward)
                                    <option value="{{ $ward->id }}" {{ old('ward_id', $userToEdit->ward_id) == $ward->id ? 'selected' : '' }}>
                                        {{ $ward->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('ward_id')
                                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- School selection -->
                <div id="school-section" style="display: none;">
                    <div class="space-y-4">
                        <!-- Province selection for school -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Tỉnh/Thành phố *</label>
                            <select id="school-province-select" class="w-full rounded-lg border px-3 py-2 @error('school_province_id') border-rose-300 @enderror" onchange="loadWardsByProvinceForSchool(this.value)">
                                <option value="">-- Chọn tỉnh/thành phố --</option>
                                @foreach($provinces as $province)
                                    <option value="{{ $province->id }}" {{ old('school_province_id', $userToEdit->province_id) == $province->id ? 'selected' : '' }}>
                                        {{ $province->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('school_province_id')
                                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- Ward selection for school -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Xã/Phường *</label>
                            <select id="school-ward-select" class="w-full rounded-lg border px-3 py-2 @error('school_ward_id') border-rose-300 @enderror" onchange="loadSchoolsByWard(this.value)">
                                <option value="">-- Chọn xã/phường --</option>
                                @foreach($wards as $ward)
                                    <option value="{{ $ward->id }}" {{ old('school_ward_id', $userToEdit->ward_id) == $ward->id ? 'selected' : '' }}>
                                        {{ $ward->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('school_ward_id')
                                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <!-- School selection -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Trường học *</label>
                            <select id="school-select" name="school_id" class="w-full rounded-lg border px-3 py-2 @error('school_id') border-rose-300 @enderror">
                                <option value="">-- Chọn trường học --</option>
                                @foreach($schools as $school)
                                    <option value="{{ $school->id }}" {{ old('school_id', $userToEdit->school_id) == $school->id ? 'selected' : '' }}>
                                        {{ $school->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('school_id')
                                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </article>
        
        <!-- Phân quyền chức năng hiện tại -->
        <article class="card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">Phân quyền chức năng hiện tại</h2>
                <div class="flex items-center gap-4">
                    {{-- <a href="{{ route('admin.permissions.user', $userToEdit->id) }}" class="btn-soft">
                        <i class="fas fa-edit mr-2"></i> Quản lý phân quyền chức năng
                    </a> --}}
                    <div id="role-permissions-notice" class="hidden text-sm text-blue-600 bg-blue-50 px-3 py-2 rounded-lg border border-blue-200">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span id="role-permissions-text">Permissions đã được tự động tích từ vai trò được chọn</span>
                    </div>
                </div>
            </div>
            
            <div class="space-y-4">
                
                @php
                    $permissions = $userToEdit->getAllPermissions();
                    $functions = \App\Models\SystemFunction::active()->get();
                    $functionsByCategory = $functions->groupBy('category');
                    
                    // Helper function để kiểm tra function có ít nhất 1 quyền không
                    function hasAnyPermission($permissions, $functionCode) {
                        if (!isset($permissions[$functionCode])) return false;
                        $perms = $permissions[$functionCode];
                        return ($perms['view'] ?? false) || 
                               ($perms['create'] ?? false) || 
                               ($perms['edit'] ?? false) || 
                               ($perms['delete'] ?? false);
                    }
                    
                    // Helper function để kiểm tra function có tất cả 4 quyền không
                    function hasAllPermissions($permissions, $functionCode) {
                        if (!isset($permissions[$functionCode])) return false;
                        $perms = $permissions[$functionCode];
                        return ($perms['view'] ?? false) && 
                               ($perms['create'] ?? false) && 
                               ($perms['edit'] ?? false) && 
                               ($perms['delete'] ?? false);
                    }
                @endphp
                
                @foreach($categories as $category)
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium text-slate-800" style="color: {{ $category->color }}">{{ $category->name }}</h3>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" 
                                       class="category-checkbox rounded" 
                                       data-category="{{ $category->code }}"
                                       onchange="toggleCategoryPermissions('{{ $category->code }}')">
                                <span class="ml-2 text-sm font-medium">Tích tất cả</span>
                            </label>
                        </div>
                        
                        <div class="space-y-3">
                            @foreach($functionsByCategory[$category->code] ?? [] as $function)
                                <div class="border rounded-lg p-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-slate-800 mb-1">{{ $function->name }}</h4>
                                            <p class="text-sm text-slate-500 mb-2">{{ $function->description }}</p>
                                            <div class="flex gap-4">
                                                @if(hasAnyPermission($permissions, $function->code))
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        ✓ Đã cấp quyền
                                                    </span>
                                                @else
                                                    <span class="text-slate-400 text-xs">Chưa có quyền</span>
                                                @endif
                                            </div>
                                        </div>
                                        <label class="flex items-center cursor-pointer ml-4">
                                            <input type="checkbox" 
                                                   class="function-checkbox rounded" 
                                                   data-function="{{ $function->code }}"
                                                   data-category="{{ $category->code }}"
                                                   onchange="toggleFunctionPermissions('{{ $function->code }}')"
                                                   name="permissions[{{ $function->code }}]"
                                                   value="1"
                                                   @if(hasAnyPermission($permissions, $function->code)) checked @endif>
                                            <span class="ml-2 text-sm font-medium text-slate-600">Cấp quyền</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                
        </article>
        
        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary">
                <i class="fas fa-save mr-2"></i> Cập nhật người dùng
            </button>
            <a href="{{ route('admin.permissions.index') }}" class="btn-soft">
                <i class="fas fa-times mr-2"></i> Hủy
            </a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
// Load permissions của role được chọn
function loadRolePermissions() {
    const roleName = document.querySelector('select[name="role"]').value;
    
    console.log('Loading permissions for role name:', roleName);
    
    if (!roleName) {
        // Nếu không chọn role, bỏ tích tất cả permissions
        clearAllPermissions();
        return;
    }
    
    // Hiển thị loading
    showLoadingNotice();
    
    // Gọi API để lấy permissions của role
    fetch(`/api/roles/${roleName}/permissions`)
        .then(response => {
            console.log('API response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API response data:', data);
            if (data.ok && data.data && data.data.permissions) {
                console.log('Applying permissions:', data.data.permissions);
                applyRolePermissions(data.data.permissions);
            } else {
                console.error('Error loading role permissions:', data.message);
                clearAllPermissions();
            }
        })
        .catch(error => {
            console.error('Error fetching role permissions:', error);
            clearAllPermissions();
        });
}

// Hiển thị thông báo loading
function showLoadingNotice() {
    const notice = document.getElementById('role-permissions-notice');
    const text = document.getElementById('role-permissions-text');
    if (notice && text) {
        text.textContent = 'Đang tải permissions từ vai trò...';
        notice.classList.remove('hidden');
    }
}

// Áp dụng permissions của role vào form
function applyRolePermissions(rolePermissions) {
    // Bỏ tích tất cả trước
    clearAllPermissions();
    
    // Áp dụng permissions từ role
    Object.keys(rolePermissions).forEach(functionCode => {
        const permissions = rolePermissions[functionCode];
        
        // Kiểm tra xem có ít nhất 1 quyền nào không
        const hasAnyPermission = Object.values(permissions).some(hasPermission => hasPermission);
        
        if (hasAnyPermission) {
            // Tích checkbox "Cấp quyền" trước
            const functionCheckbox = document.querySelector(`input[data-function="${functionCode}"]`);
            if (functionCheckbox) {
                functionCheckbox.checked = true;
                
                // Tạo hidden inputs cho 4 quyền mà không gọi toggleFunctionPermissions
                const actions = ['view', 'create', 'edit', 'delete'];
                actions.forEach(action => {
                    let input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `permissions[${functionCode}][${action}]`;
                        input.value = '0';
                        functionCheckbox.parentNode.appendChild(input);
                    }
                    // Set giá trị dựa trên permissions thực tế
                    input.value = permissions[action] ? '1' : '0';
                });
                
                // Cập nhật hiển thị permissions
                updateFunctionPermissionsDisplayFromRole(functionCode, permissions);
            }
        }
    });
    
    // Hiển thị thông báo
    showRolePermissionsNotice();
}

// Cập nhật hiển thị permissions từ role
function updateFunctionPermissionsDisplayFromRole(functionCode, permissions) {
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const permissionsContainer = functionRow.querySelector('.flex.gap-4');
    
    const actionLabels = {
        'view': { text: 'Xem', class: 'bg-blue-100 text-blue-800 hover:bg-blue-200' },
        'create': { text: 'Tạo', class: 'bg-green-100 text-green-800 hover:bg-green-200' },
        'edit': { text: 'Sửa', class: 'bg-amber-100 text-amber-800 hover:bg-amber-200' },
        'delete': { text: 'Xóa', class: 'bg-red-100 text-red-800 hover:bg-red-200' }
    };
    
    let html = '';
    Object.keys(permissions).forEach(action => {
        if (permissions[action]) {
            const label = actionLabels[action];
            html += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${label.class} cursor-pointer" 
                          onclick="toggleIndividualPermission('${functionCode}', '${action}')" title="Click để toggle">${label.text}</span>`;
        }
    });
    
    if (html === '') {
        html = '<span class="text-slate-400 text-xs">Chưa có quyền</span>';
    }
    
    permissionsContainer.innerHTML = html;
}

// Hiển thị thông báo permissions từ role
function showRolePermissionsNotice() {
    const notice = document.getElementById('role-permissions-notice');
    const text = document.getElementById('role-permissions-text');
    if (notice && text) {
        text.textContent = 'Permissions đã được tự động tích từ vai trò được chọn';
        notice.classList.remove('hidden');
        
        // Ẩn thông báo sau 5 giây
        setTimeout(() => {
            notice.classList.add('hidden');
        }, 5000);
    }
}

// Ẩn thông báo permissions từ role
function hideRolePermissionsNotice() {
    const notice = document.getElementById('role-permissions-notice');
    if (notice) {
        notice.classList.add('hidden');
    }
}

// Bỏ tích tất cả permissions
function clearAllPermissions() {
    const inputs = document.querySelectorAll('input[name^="permissions["]');
    inputs.forEach(input => {
        input.value = '0';
    });
    
    // Ẩn thông báo
    hideRolePermissionsNotice();
}

function toggleCategoryPermissions(category) {
    const categoryCheckbox = document.querySelector(`input[data-category="${category}"].category-checkbox`);
    const functionCheckboxes = document.querySelectorAll(`input[data-category="${category}"].function-checkbox`);
    
    if (categoryCheckbox.checked) {
        // Tích tất cả functions trong category
        functionCheckboxes.forEach(checkbox => {
            checkbox.checked = true;
            toggleFunctionPermissions(checkbox.dataset.function);
        });
                } else {
        // Bỏ tích tất cả functions trong category
        functionCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
            toggleFunctionPermissions(checkbox.dataset.function);
        });
    }
}

function toggleFunctionPermissions(functionCode) {
    const functionCheckbox = document.querySelector(`input[data-function="${functionCode}"]`);
    const isChecked = functionCheckbox.checked;
    
    // Tạo hoặc cập nhật hidden inputs cho 4 quyền
    const actions = ['view', 'create', 'edit', 'delete'];
    actions.forEach(action => {
        let input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = `permissions[${functionCode}][${action}]`;
            input.value = '1';
            functionCheckbox.parentNode.appendChild(input);
        }
        input.value = isChecked ? '1' : '0';
    });
    
    // Cập nhật hiển thị các quyền con
    updateFunctionPermissionsDisplay(functionCode, isChecked);
    
    // Ẩn thông báo khi user thay đổi permissions thủ công
    hideRolePermissionsNotice();
}

function updateFunctionPermissionsDisplay(functionCode, isChecked) {
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const permissionsContainer = functionRow.querySelector('.flex.gap-4');
    
    if (isChecked) {
        // Hiển thị tất cả 4 quyền khi tích nhanh với khả năng click để toggle
        permissionsContainer.innerHTML = `
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 cursor-pointer hover:bg-blue-200" 
                  onclick="toggleIndividualPermission('${functionCode}', 'view')" title="Click để toggle">Xem</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 cursor-pointer hover:bg-green-200" 
                  onclick="toggleIndividualPermission('${functionCode}', 'create')" title="Click để toggle">Tạo</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 cursor-pointer hover:bg-amber-200" 
                  onclick="toggleIndividualPermission('${functionCode}', 'edit')" title="Click để toggle">Sửa</span>
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 cursor-pointer hover:bg-red-200" 
                  onclick="toggleIndividualPermission('${functionCode}', 'delete')" title="Click để toggle">Xóa</span>
        `;
    } else {
        // Hiển thị quyền thực tế từ database
        updateFunctionPermissionsDisplayFromData(functionCode);
    }
}

function updateFunctionPermissionsDisplayFromData(functionCode) {
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const permissionsContainer = functionRow.querySelector('.flex.gap-4');
    
    // Lấy dữ liệu từ server (đã có sẵn trong Blade template)
    const permissions = @json($permissions);
    const functionPermissions = permissions[functionCode] || {};
    
    const actionLabels = {
        'view': { text: 'Xem', class: 'bg-blue-100 text-blue-800 hover:bg-blue-200' },
        'create': { text: 'Tạo', class: 'bg-green-100 text-green-800 hover:bg-green-200' },
        'edit': { text: 'Sửa', class: 'bg-amber-100 text-amber-800 hover:bg-amber-200' },
        'delete': { text: 'Xóa', class: 'bg-red-100 text-red-800 hover:bg-red-200' }
    };
    
    let html = '';
    if (functionPermissions.view) {
        const label = actionLabels['view'];
        html += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${label.class} cursor-pointer" 
                      onclick="toggleIndividualPermission('${functionCode}', 'view')" title="Click để toggle">${label.text}</span>`;
    }
    if (functionPermissions.create) {
        const label = actionLabels['create'];
        html += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${label.class} cursor-pointer" 
                      onclick="toggleIndividualPermission('${functionCode}', 'create')" title="Click để toggle">${label.text}</span>`;
    }
    if (functionPermissions.edit) {
        const label = actionLabels['edit'];
        html += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${label.class} cursor-pointer" 
                      onclick="toggleIndividualPermission('${functionCode}', 'edit')" title="Click để toggle">${label.text}</span>`;
    }
    if (functionPermissions.delete) {
        const label = actionLabels['delete'];
        html += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${label.class} cursor-pointer" 
                      onclick="toggleIndividualPermission('${functionCode}', 'delete')" title="Click để toggle">${label.text}</span>`;
    }
    
    if (html === '') {
        html = '<span class="text-slate-400 text-xs">Chưa có quyền</span>';
    }
    
    permissionsContainer.innerHTML = html;
}

function toggleIndividualPermission(functionCode, action) {
    // Tìm input tương ứng
    const input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
    if (input) {
        // Toggle quyền này (nếu đang có thì bỏ, nếu chưa có thì thêm)
        if (input.value === '1') {
            input.value = '0'; // Bỏ tích
                } else {
            input.value = '1'; // Thêm tích
        }
        
        // Cập nhật hiển thị
        updateFunctionPermissionsDisplayAfterIndividualToggle(functionCode);
        
        // Kiểm tra xem còn quyền nào không
        const actions = ['view', 'create', 'edit', 'delete'];
        const hasAnyPermission = actions.some(action => {
            const actionInput = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
            return actionInput && actionInput.value === '1';
        });
        
        // Cập nhật trạng thái checkbox "Tích nhanh"
        const functionCheckbox = document.querySelector(`input[data-function="${functionCode}"]`);
        functionCheckbox.checked = hasAnyPermission;
        
        // Cập nhật trạng thái category checkbox
        updateCategoryCheckboxState(functionCode);
    }
}

function updateFunctionPermissionsDisplayAfterIndividualToggle(functionCode) {
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const permissionsContainer = functionRow.querySelector('.flex.gap-4');
    
    // Lấy trạng thái hiện tại của các quyền
    const actions = ['view', 'create', 'edit', 'delete'];
    const actionLabels = {
        'view': { text: 'Xem', class: 'bg-blue-100 text-blue-800 hover:bg-blue-200' },
        'create': { text: 'Tạo', class: 'bg-green-100 text-green-800 hover:bg-green-200' },
        'edit': { text: 'Sửa', class: 'bg-amber-100 text-amber-800 hover:bg-amber-200' },
        'delete': { text: 'Xóa', class: 'bg-red-100 text-red-800 hover:bg-red-200' }
    };
    
    let html = '';
    actions.forEach(action => {
        const input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
        if (input && input.value === '1') {
            const label = actionLabels[action];
            html += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${label.class} cursor-pointer" 
                          onclick="toggleIndividualPermission('${functionCode}', '${action}')" title="Click để toggle">${label.text}</span>`;
        }
    });
    
    if (html === '') {
        html = '<span class="text-slate-400 text-xs">Chưa có quyền</span>';
    }
    
    permissionsContainer.innerHTML = html;
}

function updateCategoryCheckboxState(functionCode) {
    // Tìm category của function này
    const functionRow = document.querySelector(`input[data-function="${functionCode}"]`).closest('.border');
    const categoryRow = functionRow.closest('.bg-slate-50');
    const categoryCheckbox = categoryRow.querySelector('.category-checkbox');
    
    if (categoryCheckbox) {
        // Kiểm tra tất cả functions trong category này
        const allFunctionCheckboxes = categoryRow.querySelectorAll('input[data-function]');
        const allChecked = Array.from(allFunctionCheckboxes).every(checkbox => checkbox.checked);
        categoryCheckbox.checked = allChecked;
    }
}

// Cập nhật trạng thái category checkbox khi thay đổi function permissions
document.addEventListener('DOMContentLoaded', function() {
    const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
    
    categoryCheckboxes.forEach(categoryCheckbox => {
        const category = categoryCheckbox.dataset.category;
        const functionCheckboxes = document.querySelectorAll(`input[data-category="${category}"].function-checkbox`);
        
        // Cập nhật trạng thái category checkbox dựa trên function permissions
        function updateCategoryCheckbox() {
            const checkedCount = Array.from(functionCheckboxes).filter(cb => cb.checked).length;
            const totalCount = functionCheckboxes.length;
            
            if (checkedCount === 0) {
                categoryCheckbox.checked = false;
                categoryCheckbox.indeterminate = false;
            } else if (checkedCount === totalCount) {
                categoryCheckbox.checked = true;
                categoryCheckbox.indeterminate = false;
            } else {
                categoryCheckbox.checked = false;
                categoryCheckbox.indeterminate = true;
            }
        }
        
        // Lắng nghe thay đổi trên function permissions
        functionCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateCategoryCheckbox);
        });
        
        // Cập nhật trạng thái ban đầu
        updateCategoryCheckbox();
    });
    
    // Khởi tạo hidden inputs và hiển thị cho tất cả functions
    const functionCheckboxes = document.querySelectorAll('.function-checkbox');
    functionCheckboxes.forEach(checkbox => {
        const functionCode = checkbox.dataset.function;
        const isChecked = checkbox.checked;
        
        // Lấy quyền thực tế từ server (được truyền từ PHP)
        const serverPermissions = @json($permissions);
        const functionPermissions = serverPermissions[functionCode] || {};
        
        const actions = ['view', 'create', 'edit', 'delete'];
        actions.forEach(action => {
            let input = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = `permissions[${functionCode}][${action}]`;
                input.value = '0';
                checkbox.parentNode.appendChild(input);
            }
            
            // Set giá trị dựa trên quyền thực tế từ server
            const hasPermission = functionPermissions[action] || false;
            input.value = hasPermission ? '1' : '0';
        });
        
        // Hiển thị trạng thái thực tế từ database
        updateFunctionPermissionsDisplayFromData(functionCode);
    });
    
    // Xử lý form submission
    document.querySelector('form').addEventListener('submit', function(e) {
        const permissions = {};
        
        // Thu thập permissions từ hidden inputs
        this.querySelectorAll('input[name^="permissions["]').forEach(input => {
            const name = input.name;
            const matches = name.match(/permissions\[([^\]]+)\]\[([^\]]+)\]/);
            if (matches) {
                const functionCode = matches[1];
                const action = matches[2];
                if (!permissions[functionCode]) {
                    permissions[functionCode] = {};
                }
                permissions[functionCode][action] = input.value === '1';
            }
        });
        
        // Thêm permissions vào form data
        const permissionsInput = document.createElement('input');
        permissionsInput.type = 'hidden';
        permissionsInput.name = 'permissions';
        permissionsInput.value = JSON.stringify(permissions);
        this.appendChild(permissionsInput);
    });
});

// JavaScript functions for role level management (copied from create.blade.php)
function onRoleLevelChange(roleLevel) {
    // Hide all sections first
    document.getElementById('province-section').style.display = 'none';
    document.getElementById('ward-section').style.display = 'none';
    document.getElementById('school-section').style.display = 'none';

    // Clear all dependent dropdowns
    document.getElementById('ward-select').innerHTML = '<option value="">-- Chọn xã/phường --</option>';
    document.getElementById('school-ward-select').innerHTML = '<option value="">-- Chọn xã/phường --</option>';
    document.getElementById('school-select').innerHTML = '<option value="">-- Chọn trường học --</option>';

    // Show relevant section based on role level
    switch(roleLevel) {
        case 'province':
            document.getElementById('province-section').style.display = 'block';
            break;
        case 'ward':
            document.getElementById('ward-section').style.display = 'block';
            break;
        case 'school':
            document.getElementById('school-section').style.display = 'block';
            break;
    }
}

// Load wards by province for ward role
function loadWardsByProvince(provinceId) {
    const wardSelect = document.getElementById('ward-select');
    const currentWardId = '{{ $userToEdit->ward_id }}';
    
    wardSelect.innerHTML = '<option value="">-- Chọn xã/phường --</option>';
    
    if (!provinceId) return;
    
    fetch(`/admin/geo/children/${provinceId}`)
        .then(response => response.json())
        .then(data => {
            data.children.forEach(ward => {
                const option = document.createElement('option');
                option.value = ward.id;
                option.textContent = ward.name;
                if (ward.id == currentWardId) {
                    option.selected = true;
                }
                wardSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading wards:', error));
}

// Load wards by province for school role
function loadWardsByProvinceForSchool(provinceId) {
    const wardSelect = document.getElementById('school-ward-select');
    const schoolSelect = document.getElementById('school-select');
    const currentWardId = '{{ $userToEdit->ward_id }}';
    
    wardSelect.innerHTML = '<option value="">-- Chọn xã/phường --</option>';
    schoolSelect.innerHTML = '<option value="">-- Chọn trường học --</option>';
    
    if (!provinceId) return;
    
    fetch(`/admin/geo/children/${provinceId}`)
        .then(response => response.json())
        .then(data => {
            data.children.forEach(ward => {
                const option = document.createElement('option');
                option.value = ward.id;
                option.textContent = ward.name;
                if (ward.id == currentWardId) {
                    option.selected = true;
                    // Load schools for this ward
                    loadSchoolsByWard(ward.id);
                }
                wardSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading wards:', error));
}

// Load schools by ward
function loadSchoolsByWard(wardId) {
    const schoolSelect = document.getElementById('school-select');
    const currentSchoolId = '{{ $userToEdit->school_id }}';
    
    schoolSelect.innerHTML = '<option value="">-- Chọn trường học --</option>';
    
    if (!wardId) return;
    
    fetch(`/api/schools/${wardId}`)
        .then(response => response.json())
        .then(data => {
            data.forEach(school => {
                const option = document.createElement('option');
                option.value = school.id;
                option.textContent = school.name;
                if (school.id == currentSchoolId) {
                    option.selected = true;
                }
                schoolSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading schools:', error));
}

// Initialize role level section on page load
document.addEventListener('DOMContentLoaded', function() {
    const currentRoleLevel = '{{ $userToEdit->role_level }}';
    if (currentRoleLevel) {
        onRoleLevelChange(currentRoleLevel);
        
        // Load existing data based on current role level
        const currentProvinceId = '{{ $userToEdit->province_id }}';
        const currentWardId = '{{ $userToEdit->ward_id }}';
        const currentSchoolId = '{{ $userToEdit->school_id }}';
        
        if (currentRoleLevel === 'ward' && currentProvinceId) {
            // Set province first
            document.getElementById('ward-province-select').value = currentProvinceId;
            // Load wards and select current ward
            loadWardsByProvince(currentProvinceId);
        } else if (currentRoleLevel === 'school' && currentProvinceId) {
            // Set province first
            document.getElementById('school-province-select').value = currentProvinceId;
            // Load wards and schools, then select current values
            loadWardsByProvinceForSchool(currentProvinceId);
        } else if (currentRoleLevel === 'province' && currentProvinceId) {
            document.querySelector('select[name="province_id"]').value = currentProvinceId;
        }
    }
    
    // Handle form submission for role level data
    document.querySelector('form').addEventListener('submit', function(e) {
        const roleLevel = document.querySelector('select[name="role_level"]').value;
        
        // Xóa các hidden inputs cũ nếu có
        const existingHiddenInputs = this.querySelectorAll('input[type="hidden"][name="province_id"], input[type="hidden"][name="ward_id"]');
        existingHiddenInputs.forEach(input => input.remove());
        
        if (roleLevel === 'province') {
            // Đối với province, sử dụng giá trị từ select box có sẵn
            const provinceId = document.querySelector('select[name="province_id"]').value;
            console.log('Province role - province_id:', provinceId);
        } else if (roleLevel === 'ward') {
            // Lấy province_id từ ward-province-select và ward_id từ ward-select
            const wardProvinceId = document.getElementById('ward-province-select').value;
            const wardId = document.getElementById('ward-select').value;
            
            console.log('Ward role - wardProvinceId:', wardProvinceId, 'wardId:', wardId);
            
            // Tạo hidden inputs
            const provinceInput = document.createElement('input');
            provinceInput.type = 'hidden';
            provinceInput.name = 'province_id';
            provinceInput.value = wardProvinceId;
            this.appendChild(provinceInput);
            
            const wardInput = document.createElement('input');
            wardInput.type = 'hidden';
            wardInput.name = 'ward_id';
            wardInput.value = wardId;
            this.appendChild(wardInput);
        } else if (roleLevel === 'school') {
            // Lấy province_id từ school-province-select, ward_id từ school-ward-select
            const schoolProvinceId = document.getElementById('school-province-select').value;
            const schoolWardId = document.getElementById('school-ward-select').value;
            const schoolId = document.getElementById('school-select').value;
            
            console.log('School role - schoolProvinceId:', schoolProvinceId, 'schoolWardId:', schoolWardId, 'schoolId:', schoolId);
            
            // Tạo hidden inputs
            const provinceInput = document.createElement('input');
            provinceInput.type = 'hidden';
            provinceInput.name = 'province_id';
            provinceInput.value = schoolProvinceId;
            this.appendChild(provinceInput);
            
            const wardInput = document.createElement('input');
            wardInput.type = 'hidden';
            wardInput.name = 'ward_id';
            wardInput.value = schoolWardId;
            this.appendChild(wardInput);
        }
    });
});
</script>
@endpush