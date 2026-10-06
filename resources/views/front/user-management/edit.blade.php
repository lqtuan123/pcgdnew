@extends('layouts.front')

@section('title', 'Chỉnh sửa người dùng')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="space-y-8">
    <div class="flex items-center justify-between">
        <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">Chỉnh sửa người dùng</h1>
                <p class="mt-2 text-slate-600 dark:text-slate-400">Cập nhật thông tin và phân quyền cho {{ $userToEdit->name }}</p>
        </div>
        <div>
                <a href="{{ route('front.user-management.index') }}" class="inline-flex items-center px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Quay lại
            </a>
        </div>
    </div>

        <form method="POST" action="{{ route('front.user-management.update', $userToEdit->id) }}" class="space-y-8">
            @csrf
            @method('PUT')
            
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6">Thông tin cơ bản</h3>
                
                <div class="space-y-4">
                    <div class="flex items-center gap-4">
                        <label for="name" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Họ và tên <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="{{ old('name', $userToEdit->name) }}"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('name') border-red-400 @enderror" 
                            placeholder="Nhập họ và tên đầy đủ"
                            required
                        >
                    @error('name')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                </div>

                    <div class="flex items-center gap-4">
                        <label for="email" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="{{ old('email', $userToEdit->email) }}"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('email') border-red-400 @enderror" 
                            placeholder="user@gmail.com"
                            required
                        >
                    @error('email')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                </div>

                    <div class="flex items-center gap-4">
                        <label for="password" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Mật khẩu mới
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            value="{{ old('password') }}"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('password') border-red-400 @enderror" 
                            placeholder="Nhập mật khẩu mới (để trống nếu không muốn thay đổi)"
                        >
                        <div class="text-xs text-slate-500 dark:text-slate-400 ml-2">
                            Để trống nếu không muốn thay đổi mật khẩu
                        </div>
                    @error('password')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                </div>

                    <div class="flex items-center gap-4">
                        <label for="password_confirmation" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Xác nhận mật khẩu
                        </label>
                        <input 
                            type="password" 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            value="{{ old('password_confirmation') }}"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('password_confirmation') border-red-400 @enderror" 
                            placeholder="Nhập lại mật khẩu mới"
                        >
                    @error('password_confirmation')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                </div>

                    <div class="flex items-center gap-4">
                        <label for="phone_number" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Số điện thoại
                        </label>
                        <input 
                            type="tel" 
                            id="phone_number" 
                            name="phone_number" 
                            value="{{ old('phone_number', $userToEdit->phone_number) }}"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('phone_number') border-red-400 @enderror" 
                            placeholder="0123 456 789"
                        >
                    @error('phone_number')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                </div>

                    <div class="flex items-center gap-4">
                        <label for="role_id" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Quyền truy cập <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="role_id" 
                            name="role_id" 
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('role_id') border-red-400 @enderror"
                            required
                        >
                            <option value="">Chọn quyền truy cập</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id', $userToEdit->role_id) == $role->id ? 'selected' : '' }}>
                                {{ $role->display_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                    @enderror
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6">Cấp phòng ban</h3>
                
                @php
                    $currentUser = auth()->user();
                @endphp
                
                @if($currentUser->role_id == 1)
                    <div class="mb-4 p-3 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg">
                        <p class="text-sm text-purple-800 dark:text-purple-200">
                            <i class="fas fa-crown mr-2"></i>
                            <strong>Superadmin:</strong> Bạn có thể chỉnh sửa tài khoản cho <strong>tất cả địa giới</strong> trong hệ thống. Không có giới hạn về tỉnh/thành phố, xã/phường hay trường học.
                        </p>
                    </div>
                @elseif($currentUser->role_level === 'province')
                    <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            Bạn có thể chỉnh sửa tài khoản cấp <strong>Tỉnh/Thành phố</strong> (tỉnh của bạn), cấp <strong>Xã/Phường</strong> (trong tỉnh của bạn) hoặc cấp <strong>Trường học</strong> (trong tỉnh của bạn).
                        </p>
                    </div>
                @elseif($currentUser->role_level === 'ward')
                    <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <p class="text-sm text-green-800 dark:text-green-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            Bạn có thể chỉnh sửa tài khoản cấp <strong>Xã/Phường</strong> (chỉ xã của bạn) hoặc cấp <strong>Trường học</strong> (chỉ trong xã của bạn).
                        </p>
                    </div>
                @elseif($currentUser->role_level === 'school')
                    <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            Bạn có thể chỉnh sửa tài khoản cấp <strong>Trường học</strong> (chỉ trong trường của bạn).
                        </p>
                    </div>
                @endif

                <div class="space-y-4">
                    @php
                        $currentUser = auth()->user();
                        $availableLevels = [];
                        
                        if ($currentUser->role_id == 1) {
                            // Superadmin có thể chỉnh sửa tất cả cấp độ
                            $availableLevels = ['province', 'ward', 'school'];
                        } elseif ($currentUser->role_level === 'province') {
                            $availableLevels = ['province', 'ward', 'school'];
                        } elseif ($currentUser->role_level === 'ward') {
                            $availableLevels = ['ward', 'school'];
                        } elseif ($currentUser->role_level === 'school') {
                            $availableLevels = ['school'];
                        }
                    @endphp
                    
                    <div class="flex items-center gap-4">
                        <label for="role_level" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Cấp độ <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="role_level" 
                            name="role_level"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('role_level') border-red-400 @enderror"
                            required
                            onchange="updateGeoFields()"
                        >
                            <option value="">Chọn cấp độ</option>
                            @if(in_array('province', $availableLevels))
                                <option value="province" {{ old('role_level', $userToEdit->role_level) === 'province' ? 'selected' : '' }}>Tỉnh/Thành phố</option>
                            @endif
                            @if(in_array('ward', $availableLevels))
                                <option value="ward" {{ old('role_level', $userToEdit->role_level) === 'ward' ? 'selected' : '' }}>Xã/Phường</option>
                            @endif
                            @if(in_array('school', $availableLevels))
                                <option value="school" {{ old('role_level', $userToEdit->role_level) === 'school' ? 'selected' : '' }}>Trường học</option>
                            @endif
                        </select>
                        @error('role_level')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center gap-4">
                        <label for="province_id" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Tỉnh/TP <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="province_id" 
                            name="province_id" 
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 disabled:bg-slate-100 dark:disabled:bg-slate-600 disabled:text-slate-500 @error('province_id') border-red-400 @enderror"
                            required
                            onchange="loadWards()"
                        >
                            <option value="">Chọn cấp độ trước</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}" {{ old('province_id', $userToEdit->province_id) == $province->id ? 'selected' : '' }}>
                                    {{ $province->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('province_id')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div id="ward_section" class="hidden flex items-center gap-4">
                        <label for="ward_id" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Xã/Phường <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="ward_id" 
                            name="ward_id"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('ward_id') border-red-400 @enderror"
                            onchange="loadSchools()"
                        >
                            <option value="">Chọn xã/phường</option>
                            @foreach($wards as $ward)
                                <option value="{{ $ward->id }}" {{ old('ward_id', $userToEdit->ward_id) == $ward->id ? 'selected' : '' }}>
                                    {{ $ward->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('ward_id')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div id="school_section" class="hidden flex items-center gap-4">
                        <label for="school_id" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Trường học <span class="text-red-500">*</span>
                        </label>
                        <select 
                            id="school_id" 
                            name="school_id"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('school_id') border-red-400 @enderror"
                        >
                            <option value="">Chọn trường học</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" {{ old('school_id', $userToEdit->school_id) == $school->id ? 'selected' : '' }}>
                                    {{ $school->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('school_id')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6">Vai trò có thể cấp</h3>
                
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
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
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            >
                            <label for="assignable_role_{{ $role->id }}" class="text-sm font-medium text-gray-900 dark:text-gray-300">
                                {{ $role->display_name }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6">Phân quyền chức năng</h3>

                <div class="space-y-4">
                    @foreach($categories as $category)
                        @php
                            $categoryFunctions = $functions->where('category', $category->code);
                            $hasVisibleFunctions = false;
                            
                            // Kiểm tra xem có function nào trong category này được hiển thị không
                            foreach($categoryFunctions as $function) {
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
                                    $hasVisibleFunctions = true;
                                    break;
                                }
                            }
                        @endphp
                        
                        @if($hasVisibleFunctions)
                            <div class="border border-slate-200 dark:border-slate-600 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200" style="color: {{ $category->color ?? '#6366f1' }}">{{ $category->name }}</h4>
                                </div>
                                
                                <div class="space-y-2">
                                    @foreach($categoryFunctions as $function)
                                    <div class="flex items-center justify-between p-2 bg-slate-50 dark:bg-slate-700/50 rounded">
                                        <div class="flex-1">
                                            <h5 class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ $function->name }}</h5>
                                            @if($function->description)
                                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $function->description }}</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2 ml-4">
                                            <button type="button" onclick="toggleFunctionAll('{{ $function->code }}')" 
                                                    class="px-2 py-1 text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 border border-slate-300 dark:border-slate-600 rounded hover:bg-slate-50 dark:hover:bg-slate-700">
                                                Tất cả
                                            </button>
                                            <div class="flex items-center gap-1">
                                                @if(isset($currentUserPermissions[$function->code]['view']) && $currentUserPermissions[$function->code]['view'])
                                                    <label class="flex items-center cursor-pointer">
                                                        <input type="checkbox" 
                                                               class="permission-checkbox w-3 h-3 rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500" 
                                                               data-function="{{ $function->code }}" 
                                                               data-action="view"
                                                               onchange="updatePermissionInput('{{ $function->code }}', 'view')">
                                                        <span class="ml-1 text-xs text-slate-600 dark:text-slate-400">Xem</span>
                                                    </label>
                                                @endif
                                                @if(isset($currentUserPermissions[$function->code]['create']) && $currentUserPermissions[$function->code]['create'])
                                                    <label class="flex items-center cursor-pointer">
                                                        <input type="checkbox" 
                                                               class="permission-checkbox w-3 h-3 rounded border-slate-300 dark:border-slate-600 text-green-600 focus:ring-green-500" 
                                                               data-function="{{ $function->code }}" 
                                                               data-action="create"
                                                               onchange="updatePermissionInput('{{ $function->code }}', 'create')">
                                                        <span class="ml-1 text-xs text-slate-600 dark:text-slate-400">Tạo</span>
                                                    </label>
                                                @endif
                                                @if(isset($currentUserPermissions[$function->code]['edit']) && $currentUserPermissions[$function->code]['edit'])
                                                    <label class="flex items-center cursor-pointer">
                                                        <input type="checkbox" 
                                                               class="permission-checkbox w-3 h-3 rounded border-slate-300 dark:border-slate-600 text-amber-600 focus:ring-amber-500" 
                                                               data-function="{{ $function->code }}" 
                                                               data-action="edit"
                                                               onchange="updatePermissionInput('{{ $function->code }}', 'edit')">
                                                        <span class="ml-1 text-xs text-slate-600 dark:text-slate-400">Sửa</span>
                                                    </label>
                                                @endif
                                                @if(isset($currentUserPermissions[$function->code]['delete']) && $currentUserPermissions[$function->code]['delete'])
                                                    <label class="flex items-center cursor-pointer">
                                                        <input type="checkbox" 
                                                               class="permission-checkbox w-3 h-3 rounded border-slate-300 dark:border-slate-600 text-red-600 focus:ring-red-500" 
                                                               data-function="{{ $function->code }}" 
                                                               data-action="delete"
                                                               onchange="updatePermissionInput('{{ $function->code }}', 'delete')">
                                                        <span class="ml-1 text-xs text-slate-600 dark:text-slate-400">Xóa</span>
                                                    </label>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        <!-- Hidden inputs for form submission -->
                                        <input type="hidden" name="permissions[{{ $function->code }}][view]" value="0">
                                        <input type="hidden" name="permissions[{{ $function->code }}][create]" value="0">
                                        <input type="hidden" name="permissions[{{ $function->code }}][edit]" value="0">
                                        <input type="hidden" name="permissions[{{ $function->code }}][delete]" value="0">
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <a href="{{ route('front.user-management.index') }}" class="px-6 py-3 border border-slate-300 dark:border-slate-600 rounded-xl text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 transition-all duration-200">
                    Hủy
                </a>
                @if(auth()->user()->hasPermission('quan_ly_nguoi_dung', 'edit'))
                    <button type="submit" class="px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                        Cập nhật người dùng
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>

<script>
// Get current user permissions from server
const currentPermissions = @json($userToEdit->getAllPermissions());


// Toggle all permissions for a function
function toggleFunctionAll(functionCode) {
    const checkboxes = document.querySelectorAll(`[data-function="${functionCode}"]`);
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
        updatePermissionInput(functionCode, checkbox.dataset.action);
    });
}

// Update hidden input when checkbox changes
function updatePermissionInput(functionCode, action) {
    const checkbox = document.querySelector(`[data-function="${functionCode}"][data-action="${action}"]`);
    const hiddenInput = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
    
    if (checkbox && hiddenInput) {
        hiddenInput.value = checkbox.checked ? '1' : '0';
    }
}

// Initialize checkboxes based on current permissions
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    checkboxes.forEach(checkbox => {
        const functionCode = checkbox.dataset.function;
        const action = checkbox.dataset.action;
        
        // Check if user has this permission
        if (currentPermissions[functionCode] && currentPermissions[functionCode][action]) {
            checkbox.checked = true;
        }
        
        updatePermissionInput(functionCode, action);
    });
    
    // Initialize geo fields
    updateGeoFields();
});

// Form submission
document.querySelector('form').addEventListener('submit', function(e) {
    const permissions = {};
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
    
    const permissionsInput = document.createElement('input');
    permissionsInput.type = 'hidden';
    permissionsInput.name = 'permissions';
    permissionsInput.value = JSON.stringify(permissions);
    this.appendChild(permissionsInput);
});

function loadWards() {
    const provinceId = document.getElementById('province_id').value;
    const wardSelect = document.getElementById('ward_id');
    const schoolSelect = document.getElementById('school_id');
    
    // Get current ward ID from the user data (from PHP)
    const currentWardId = '{{ $userToEdit->ward_id }}';
    
    console.log('loadWards called with provinceId:', provinceId, 'currentWardId:', currentWardId);
    
    wardSelect.innerHTML = '<option value="">Chọn xã/phường</option>';
    schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
    
    if (provinceId) {
        return fetch(`/api/wards/${provinceId}`)
            .then(response => response.json())
            .then(data => {
                console.log('Wards data received:', data);
                data.forEach(ward => {
                    const option = document.createElement('option');
                    option.value = ward.id;
                    option.textContent = ward.name;
                    if (ward.id == currentWardId) {
                        option.selected = true;
                        console.log('Selected ward:', ward.name, 'ID:', ward.id);
                    }
                    wardSelect.appendChild(option);
                });
                
                // If we had a selected ward, load schools for it
                if (currentWardId) {
                    console.log('Loading schools after wards loaded for ward:', currentWardId);
                    loadSchools();
                }
            })
            .catch(error => console.error('Error loading wards:', error));
    }
    
    return Promise.resolve();
}

function loadSchools() {
    const wardId = document.getElementById('ward_id').value;
    const schoolSelect = document.getElementById('school_id');
    
    console.log('loadSchools called with wardId:', wardId);
    
    // Get current school ID from the user data (from PHP)
    const currentSchoolId = '{{ $userToEdit->school_id }}';
    
    schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
    
    if (wardId) {
        console.log('Fetching schools for ward:', wardId);
        fetch(`/api/schools/${wardId}`)
            .then(response => response.json())
            .then(data => {
                console.log('Schools data received:', data);
                data.forEach(school => {
                    const option = document.createElement('option');
                    option.value = school.id;
                    option.textContent = school.name;
                    if (school.id == currentSchoolId) {
                        option.selected = true;
                        console.log('Selected school:', school.name, 'ID:', school.id);
                    }
                    schoolSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error loading schools:', error));
    } else {
        console.log('No wardId provided to loadSchools');
    }
}

function updateGeoFields() {
    const roleLevel = document.getElementById('role_level').value;
    const provinceSelect = document.getElementById('province_id');
    const wardSection = document.getElementById('ward_section');
    const schoolSection = document.getElementById('school_section');
    const wardSelect = document.getElementById('ward_id');
    const schoolSelect = document.getElementById('school_id');
    
    if (roleLevel === 'province') {
        provinceSelect.disabled = false;
        wardSection.classList.add('hidden');
        schoolSection.classList.add('hidden');
        wardSelect.innerHTML = '<option value="">Chọn xã/phường</option>';
        schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
    } else if (roleLevel === 'ward') {
        provinceSelect.disabled = false;
        wardSection.classList.remove('hidden');
        schoolSection.classList.add('hidden');
        schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
    } else if (roleLevel === 'school') {
        provinceSelect.disabled = false;
        wardSection.classList.remove('hidden');
        schoolSection.classList.remove('hidden');
    } else {
        provinceSelect.disabled = true;
        wardSection.classList.add('hidden');
        schoolSection.classList.add('hidden');
        // Reset all selects when no role level selected
        provinceSelect.value = '';
        wardSelect.innerHTML = '<option value="">Chọn xã/phường</option>';
        schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    checkboxes.forEach(checkbox => {
        const functionCode = checkbox.dataset.function;
        const action = checkbox.dataset.action;
        updatePermissionInput(functionCode, action);
    });
    
    updateGeoFields();
    
    // Load initial data if user has existing geo data
    const roleLevel = document.getElementById('role_level').value;
    const provinceId = document.getElementById('province_id').value;
    
    console.log('Initial data:', { roleLevel, provinceId });
    
    if (roleLevel && provinceId) {
        // Load wards if user has province
        if (roleLevel === 'ward' || roleLevel === 'school') {
            loadWards().then(() => {
                // After wards are loaded, get the current wardId from the select
                const currentWardId = document.getElementById('ward_id').value;
                console.log('Current wardId after loading wards:', currentWardId);
                
                // Load schools if user has ward and role is school
                if (roleLevel === 'school' && currentWardId) {
                    console.log('Loading schools for ward:', currentWardId);
                    loadSchools();
                }
            });
        }
    }
});
</script>
@endsection
