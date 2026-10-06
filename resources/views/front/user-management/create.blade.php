@extends('layouts.front')

@section('title', 'Tạo người dùng mới')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="space-y-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">Tạo người dùng mới</h1>
                <p class="mt-2 text-slate-600 dark:text-slate-400">Thêm người dùng mới vào hệ thống</p>
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

        <form method="POST" action="{{ route('front.user-management.store') }}" class="space-y-8">
            @csrf
            
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
                            value="{{ old('name') }}"
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
                            value="{{ old('email') }}"
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
                            Mật khẩu <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            value="{{ old('password') }}"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('password') border-red-400 @enderror" 
                            placeholder="Nhập mật khẩu"
                            required
                        >
                        @error('password')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="flex items-center gap-4">
                        <label for="password_confirmation" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Xác nhận mật khẩu <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="password" 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            value="{{ old('password_confirmation') }}"
                            class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('password_confirmation') border-red-400 @enderror" 
                            placeholder="Nhập lại mật khẩu"
                            required
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
                            value="{{ old('phone_number') }}"
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
                            onchange="loadRolePermissions()"
                        >
                            <option value="">Chọn quyền truy cập</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" data-role-name="{{ $role->name }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
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
                <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-6">Phân quyền địa giới</h3>
                
                @php
                    $currentUser = auth()->user();
                @endphp
                
                @if($currentUser->role_id == 1)
                    <div class="mb-4 p-3 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg">
                        <p class="text-sm text-purple-800 dark:text-purple-200">
                            <i class="fas fa-crown mr-2"></i>
                            <strong>Superadmin:</strong> Bạn có thể tạo tài khoản cho <strong>tất cả địa giới</strong> trong hệ thống. Không có giới hạn về tỉnh/thành phố, xã/phường hay trường học.
                        </p>
                    </div>
                @elseif($currentUser->role_level === 'province')
                    <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            Bạn có thể tạo tài khoản cấp <strong>Tỉnh/Thành phố</strong> (tỉnh của bạn), cấp <strong>Xã/Phường</strong> (trong tỉnh của bạn) hoặc cấp <strong>Trường học</strong> (trong tỉnh của bạn). Tỉnh/TP sẽ được khóa theo tỉnh của bạn.
                        </p>
                    </div>
                @elseif($currentUser->role_level === 'ward')
                    <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <p class="text-sm text-green-800 dark:text-green-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            Bạn có thể tạo tài khoản cấp <strong>Xã/Phường</strong> (chỉ xã của bạn) hoặc cấp <strong>Trường học</strong> (chỉ trong xã của bạn). Tỉnh/TP và Xã/Phường sẽ được khóa theo địa bàn của bạn.
                        </p>
                    </div>
                @elseif($currentUser->role_level === 'school')
                    <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                        <p class="text-sm text-amber-800 dark:text-amber-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            Bạn có thể tạo tài khoản cấp <strong>Trường học</strong> (chỉ trong trường của bạn). Tất cả thông tin địa bàn sẽ được khóa theo trường của bạn.
                        </p>
                    </div>
                @endif

                <div class="space-y-4">
                    @php
                        $currentUser = auth()->user();
                        $availableLevels = [];
                        
                        if ($currentUser->role_id == 1) {
                            // Superadmin có thể tạo tất cả cấp độ
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
                                <option value="province" {{ old('role_level') === 'province' ? 'selected' : '' }}>Tỉnh/Thành phố</option>
                            @endif
                            @if(in_array('ward', $availableLevels))
                                <option value="ward" {{ old('role_level') === 'ward' ? 'selected' : '' }}>Xã/Phường</option>
                            @endif
                            @if(in_array('school', $availableLevels))
                                <option value="school" {{ old('role_level') === 'school' ? 'selected' : '' }}>Trường học</option>
                            @endif
                        </select>
                        @error('role_level')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div id="province_section" class="flex items-center gap-4" style="display: none;">
                        <label for="province_id" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Tỉnh/TP <span class="text-red-500">*</span>
                        </label>
                        @if($currentUser->role_id == 1)
                            {{-- Superadmin: có thể chọn tất cả tỉnh --}}
                            <select 
                                id="province_id" 
                                name="province_id"
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('province_id') border-red-400 @enderror"
                                onchange="loadWards()"
                            >
                                <option value="">Chọn tỉnh/thành phố</option>
                                @foreach($provinces as $province)
                                    <option value="{{ $province->id }}" {{ old('province_id') == $province->id ? 'selected' : '' }}>
                                        {{ $province->name }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif($currentUser->role_level === 'province')
                            <input 
                                type="hidden" 
                                name="province_id" 
                                value="{{ $currentUser->province_id }}"
                            >
                            <input 
                                type="text" 
                                value="{{ $currentUser->province->name ?? 'N/A' }}" 
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-400 cursor-not-allowed"
                                readonly
                            >
                        @else
                            <input 
                                type="hidden" 
                                name="province_id" 
                                value="{{ $currentUser->province_id }}"
                            >
                            <input 
                                type="text" 
                                value="{{ $currentUser->province->name ?? 'N/A' }}" 
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-400 cursor-not-allowed"
                                readonly
                            >
                        @endif
                        @error('province_id')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div id="ward_section" class="flex items-center gap-4" style="display: none;">
                        <label for="ward_id" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Xã/Phường <span class="text-red-500">*</span>
                        </label>
                        @if($currentUser->role_id == 1)
                            {{-- Superadmin: có thể chọn tất cả xã --}}
                            <select 
                                id="ward_id" 
                                name="ward_id"
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('ward_id') border-red-400 @enderror"
                                onchange="loadSchools()"
                            >
                                <option value="">Chọn xã/phường</option>
                                @foreach($wards as $ward)
                                    <option value="{{ $ward->id }}" {{ old('ward_id') == $ward->id ? 'selected' : '' }}>
                                        {{ $ward->name }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif($currentUser->role_level === 'province')
                            <select 
                                id="ward_id" 
                                name="ward_id"
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('ward_id') border-red-400 @enderror"
                                onchange="loadSchools()"
                            >
                                <option value="">Chọn xã/phường</option>
                                @foreach($wards as $ward)
                                    <option value="{{ $ward->id }}" {{ old('ward_id') == $ward->id ? 'selected' : '' }}>
                                        {{ $ward->name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input 
                                type="hidden" 
                                name="ward_id" 
                                value="{{ $currentUser->ward_id }}"
                            >
                            <input 
                                type="text" 
                                value="{{ $currentUser->ward->name ?? 'N/A' }}" 
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-400 cursor-not-allowed"
                                readonly
                            >
                        @endif
                        @error('ward_id')
                            <span class="text-sm text-red-600 dark:text-red-400">{{ $message }}</span>
                        @enderror
                    </div>

                    <div id="school_section" class="flex items-center gap-4" style="display: none;">
                        <label for="school_id" class="w-24 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Trường học <span class="text-red-500">*</span>
                        </label>
                        @if($currentUser->role_id == 1)
                            {{-- Superadmin: có thể chọn tất cả trường --}}
                            <select 
                                id="school_id" 
                                name="school_id"
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('school_id') border-red-400 @enderror"
                            >
                                <option value="">Chọn trường học</option>
                                @foreach($schools as $school)
                                    <option value="{{ $school->id }}" {{ old('school_id') == $school->id ? 'selected' : '' }}>
                                        {{ $school->name }}
                                    </option>
                                @endforeach
                            </select>
                        @elseif($currentUser->role_level === 'school')
                            <input 
                                type="hidden" 
                                name="school_id" 
                                value="{{ $currentUser->school_id }}"
                            >
                            <input 
                                type="text" 
                                value="{{ $currentUser->school->name ?? 'N/A' }}" 
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-600 text-slate-600 dark:text-slate-400 cursor-not-allowed"
                                readonly
                            >
                        @else
                            <select 
                                id="school_id" 
                                name="school_id"
                                class="flex-1 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-1 focus:ring-blue-200 dark:focus:ring-blue-800 @error('school_id') border-red-400 @enderror"
                            >
                                <option value="">Chọn trường học</option>
                                @foreach($schools as $school)
                                    <option value="{{ $school->id }}" {{ old('school_id') == $school->id ? 'selected' : '' }}>
                                        {{ $school->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
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
                        Chọn các vai trò mà tài khoản mới có thể sử dụng khi tạo tài khoản và phân quyền cho người khác.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($assignableRoles as $role)
                        <div class="flex items-center space-x-3">
                            <input 
                                type="checkbox" 
                                id="assignable_role_{{ $role->id }}" 
                                name="assignable_roles[]" 
                                value="{{ $role->id }}"
                                class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                            >
                            <label for="assignable_role_{{ $role->id }}" class="text-sm font-medium text-gray-900 dark:text-gray-300">
                                {{ $role->name }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Phân quyền chức năng</h3>
                    <div id="role-permissions-notice" class="hidden text-sm text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-3 py-2 rounded-lg border border-blue-200 dark:border-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span id="role-permissions-text">Permissions đã được tự động tích từ vai trò được chọn</span>
                    </div>
                </div>

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
                @if(auth()->user()->hasPermission('quan_ly_nguoi_dung', 'create'))
                    <button type="submit" class="px-6 py-3 border border-transparent rounded-xl text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200">
                        Tạo người dùng
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>

<script>

// Toggle all permissions for a function
function toggleFunctionAll(functionCode) {
    const checkboxes = document.querySelectorAll(`[data-function="${functionCode}"]`);
    const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
        updatePermissionInput(functionCode, checkbox.dataset.action);
    });
}

function updatePermissionInput(functionCode, action) {
    const checkbox = document.querySelector(`[data-function="${functionCode}"][data-action="${action}"]`);
    const hiddenInput = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
    
    if (checkbox && hiddenInput) {
        hiddenInput.value = checkbox.checked ? '1' : '0';
        
        // Ẩn thông báo khi user thay đổi permissions thủ công
        hideRolePermissionsNotice();
    }
}

// Load permissions của role được chọn
function loadRolePermissions() {
    const roleSelect = document.getElementById('role_id');
    const roleId = roleSelect.value;
    
    if (!roleId) {
        // Nếu không chọn role, bỏ tích tất cả permissions
        clearAllPermissions();
        return;
    }
    
    // Lấy roleName từ option được chọn
    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
    const roleName = selectedOption.getAttribute('data-role-name') || selectedOption.textContent.toLowerCase().replace(/\s+/g, '_');
    
    console.log('Loading permissions for role:', roleName, 'ID:', roleId);
    
    // Hiển thị loading
    showLoadingNotice();
    
    // Gọi API để lấy permissions của role (sử dụng roleName)
    fetch(`/api/roles/${roleName}/permissions`)
        .then(response => {
            console.log('API response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('API response data:', data);
            if (data.ok && data.data && data.data.permissions) {
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
        
        Object.keys(permissions).forEach(action => {
            if (permissions[action]) {
                const checkbox = document.querySelector(`[data-function="${functionCode}"][data-action="${action}"]`);
                const hiddenInput = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
                
                if (checkbox && hiddenInput) {
                    checkbox.checked = true;
                    hiddenInput.value = '1';
                }
            }
        });
    });
    
    // Hiển thị thông báo
    showRolePermissionsNotice();
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
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
        const functionCode = checkbox.dataset.function;
        const action = checkbox.dataset.action;
        const hiddenInput = document.querySelector(`input[name="permissions[${functionCode}][${action}]"]`);
        if (hiddenInput) {
            hiddenInput.value = '0';
        }
    });
    
    // Ẩn thông báo
    hideRolePermissionsNotice();
}

function loadWards() {
    try {
        const provinceId = document.getElementById('province_id').value;
        const wardSelect = document.getElementById('ward_id');
        const schoolSelect = document.getElementById('school_id');
        
        console.log('loadWards called with provinceId:', provinceId);
        
        // Clear school select khi thay đổi ward
        if (schoolSelect) {
            schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
        }
        
        // Đơn giản hóa: luôn gọi API để load wards
        if (wardSelect) {
            // Hiển thị loading
            wardSelect.innerHTML = '<option value="">Đang tải xã/phường...</option>';
            wardSelect.disabled = true;
            
            if (provinceId) {
                console.log('Fetching wards for province:', provinceId);
                const startTime = Date.now();
                
                fetch(`/api/wards/${provinceId}`)
                    .then(response => {
                        console.log('API response status:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        const endTime = Date.now();
                        const loadTime = endTime - startTime;
                        console.log('API response data:', data, 'Load time:', loadTime + 'ms');
                        
                        wardSelect.innerHTML = '<option value="">Chọn xã/phường</option>';
                        wardSelect.disabled = false;
                        
                        if (Array.isArray(data)) {
                            data.forEach(ward => {
                                const option = document.createElement('option');
                                option.value = ward.id;
                                option.textContent = ward.name;
                                wardSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error loading wards:', error);
                        wardSelect.innerHTML = '<option value="">Chọn xã/phường</option>';
                        wardSelect.disabled = false;
                        
                        // Thêm option lỗi để user biết
                        const errorOption = document.createElement('option');
                        errorOption.value = '';
                        errorOption.textContent = 'Lỗi tải dữ liệu xã/phường';
                        errorOption.disabled = true;
                        wardSelect.appendChild(errorOption);
                    });
            } else {
                wardSelect.innerHTML = '<option value="">Chọn xã/phường</option>';
                wardSelect.disabled = false;
            }
        }
    } catch (error) {
        console.error('loadWards function error:', error);
    }
}

function loadSchools() {
    try {
        const wardId = document.getElementById('ward_id').value;
        const schoolSelect = document.getElementById('school_id');
        
        console.log('loadSchools called with wardId:', wardId);
        
        // Đơn giản hóa: luôn gọi API để load schools
        if (schoolSelect) {
            // Hiển thị loading
            schoolSelect.innerHTML = '<option value="">Đang tải trường học...</option>';
            schoolSelect.disabled = true;
            
            if (wardId) {
                console.log('Fetching schools for ward:', wardId);
                const startTime = Date.now();
                
                fetch(`/api/schools/${wardId}`)
                    .then(response => {
                        console.log('API response status:', response.status);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        const endTime = Date.now();
                        const loadTime = endTime - startTime;
                        console.log('API response data:', data, 'Load time:', loadTime + 'ms');
                        
                        schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
                        schoolSelect.disabled = false;
                        
                        if (Array.isArray(data)) {
                            data.forEach(school => {
                                const option = document.createElement('option');
                                option.value = school.id;
                                option.textContent = school.name;
                                schoolSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error loading schools:', error);
                        schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
                        schoolSelect.disabled = false;
                        
                        // Thêm option lỗi để user biết
                        const errorOption = document.createElement('option');
                        errorOption.value = '';
                        errorOption.textContent = 'Lỗi tải dữ liệu trường học';
                        errorOption.disabled = true;
                        schoolSelect.appendChild(errorOption);
                    });
            } else {
                schoolSelect.innerHTML = '<option value="">Chọn trường học</option>';
                schoolSelect.disabled = false;
            }
        }
    } catch (error) {
        console.error('loadSchools function error:', error);
    }
}

function updateGeoFields() {
    const roleLevel = document.getElementById('role_level').value;
    const provinceSection = document.getElementById('province_section');
    const wardSection = document.getElementById('ward_section');
    const schoolSection = document.getElementById('school_section');
    
    console.log('Role level selected:', roleLevel); // Debug log
    
    // Ẩn tất cả sections trước
    if (wardSection) wardSection.style.display = 'none';
    if (schoolSection) schoolSection.style.display = 'none';
    
    if (roleLevel === 'province') {
        // Cấp tỉnh: chỉ hiển thị tỉnh
        console.log('Showing province section only');
        if (provinceSection) {
            provinceSection.style.display = 'flex';
            console.log('Province section displayed');
        }
        if (wardSection) wardSection.style.display = 'none';
        if (schoolSection) schoolSection.style.display = 'none';
    } else if (roleLevel === 'ward') {
        // Cấp xã: hiển thị tỉnh và xã
        console.log('Showing province and ward sections');
        if (provinceSection) provinceSection.style.display = 'flex';
        if (wardSection) {
            wardSection.style.display = 'flex';
            console.log('Ward section displayed');
        }
        if (schoolSection) schoolSection.style.display = 'none';
    } else if (roleLevel === 'school') {
        // Cấp trường: hiển thị tỉnh, xã và trường
        console.log('Showing all sections');
        if (provinceSection) provinceSection.style.display = 'flex';
        if (wardSection) wardSection.style.display = 'flex';
        if (schoolSection) {
            schoolSection.style.display = 'flex';
            console.log('School section displayed');
        }
    } else {
        // Không chọn cấp độ: ẩn tất cả
        console.log('Hiding all sections');
        if (provinceSection) provinceSection.style.display = 'none';
        if (wardSection) wardSection.style.display = 'none';
        if (schoolSection) schoolSection.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.permission-checkbox');
    checkboxes.forEach(checkbox => {
        const functionCode = checkbox.dataset.function;
        const action = checkbox.dataset.action;
        updatePermissionInput(functionCode, action);
    });
    
    // Khởi tạo hiển thị các trường dựa trên cấp độ user hiện tại
    const currentUserLevel = '{{ auth()->user()->role_level }}';
    console.log('Current user level:', currentUserLevel);
    
    // Debug: Kiểm tra các section có tồn tại không
    const provinceSection = document.getElementById('province_section');
    const wardSection = document.getElementById('ward_section');
    const schoolSection = document.getElementById('school_section');
    
    console.log('Province section exists:', !!provinceSection);
    console.log('Ward section exists:', !!wardSection);
    console.log('School section exists:', !!schoolSection);
    
    // Nếu user cấp xã, hiển thị sẵn trường xã
    if (currentUserLevel === 'ward') {
        const wardSection = document.getElementById('ward_section');
        if (wardSection) {
            wardSection.style.display = 'flex';
            console.log('Ward section shown for ward level user');
        }
    }
    
    // Nếu user cấp trường, hiển thị sẵn trường xã và trường
    if (currentUserLevel === 'school') {
        const wardSection = document.getElementById('ward_section');
        const schoolSection = document.getElementById('school_section');
        if (wardSection) {
            wardSection.style.display = 'flex';
            console.log('Ward section shown for school level user');
        }
        if (schoolSection) {
            schoolSection.style.display = 'flex';
            console.log('School section shown for school level user');
        }
    }
    
    updateGeoFields();
});

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
</script>
@endsection
