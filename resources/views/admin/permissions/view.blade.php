@extends('layouts.admin')

@section('title', 'Xem thông tin phân quyền')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Thông tin phân quyền</h1>
            <p class="mt-2 text-slate-600">Chi tiết quyền hạn của: <strong>{{ $user->name }}</strong></p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.permissions.user', $user->id) }}" class="btn-primary">
                <i class="fas fa-edit mr-2"></i> Chỉnh sửa quyền
            </a>
            <a href="{{ route('admin.permissions.index') }}" class="btn-soft">
                <i class="fas fa-arrow-left mr-2"></i> Quay lại
            </a>
        </div>
    </div>

    <!-- Thông tin người dùng -->
    <div class="grid md:grid-cols-3 gap-6">
        <!-- Thông tin cơ bản -->
        <div class="card">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-user mr-2 text-blue-600"></i>
                Thông tin cơ bản
            </h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Tên:</span>
                    <span class="font-medium">{{ $user->name }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Email:</span>
                    <span class="font-medium">{{ $user->email }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Vai trò:</span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $user->getRoleDisplayName() }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Trạng thái:</span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $user->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $user->is_active ? 'Hoạt động' : 'Không hoạt động' }}
                    </span>
                </div>
                @if($user->phone_number)
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">SĐT:</span>
                    <span class="font-medium">{{ $user->phone_number }}</span>
                </div>
                @endif
            </div>
        </div>

        <!-- Phân quyền vai trò -->
        <div class="card">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-shield-alt mr-2 text-green-600"></i>
                Phân quyền vai trò
            </h3>
            @if($user->userRole)
                <div class="space-y-2">
                    <div class="flex items-center justify-between p-2 bg-slate-50 rounded">
                        <div>
                            <span class="font-medium">{{ $user->userRole->display_name ?? $user->userRole->name }}</span>
                            <p class="text-xs text-slate-500">Level: {{ $user->userRole->level ?? 'N/A' }}</p>
                        </div>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            {{ $user->userRole->level ?? 'N/A' }}
                        </span>
                    </div>
                </div>
            @else
                <p class="text-slate-500 text-center py-4">Chưa có phân quyền vai trò</p>
            @endif
        </div>

        <!-- Thống kê quyền -->
        <div class="card">
            <h3 class="text-lg font-semibold mb-4 flex items-center">
                <i class="fas fa-chart-pie mr-2 text-purple-600"></i>
                Thống kê quyền
            </h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Tổng chức năng:</span>
                    <span class="font-medium">{{ $totalFunctions }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Có quyền:</span>
                    <span class="font-medium text-green-600">{{ $permissionCount }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600">Tỷ lệ:</span>
                    <span class="font-medium">{{ $permissionPercentage }}%</span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2">
                    <div class="bg-gradient-to-r from-blue-500 to-green-500 h-2 rounded-full" style="width: {{ $permissionPercentage }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chi tiết phân quyền chức năng -->
    <div class="card">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold flex items-center">
                <i class="fas fa-cogs mr-2 text-indigo-600"></i>
                Chi tiết phân quyền chức năng
            </h3>
            <div class="flex items-center gap-2">
                <button onclick="toggleAllCategories()" class="btn-soft text-sm">
                    <i class="fas fa-expand-arrows-alt mr-1"></i> Mở rộng tất cả
                </button>
                <button onclick="collapseAllCategories()" class="btn-soft text-sm">
                    <i class="fas fa-compress-arrows-alt mr-1"></i> Thu gọn tất cả
                </button>
            </div>
        </div>

        <div class="space-y-6">
            @foreach($categories as $category)
                <div class="border rounded-lg overflow-hidden">
                    <div class="bg-slate-50 px-4 py-3 border-b cursor-pointer" onclick="toggleCategory('{{ $category }}')">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-slate-800 flex items-center">
                                <i class="fas fa-chevron-down transform transition-transform duration-200" id="icon-{{ $category }}"></i>
                                <span class="ml-2">{{ ucfirst($category) }}</span>
                                <span class="ml-3 text-sm text-slate-500">
                                    ({{ $functions->where('category', $category)->count() }} chức năng)
                                </span>
                            </h4>
                            <div class="flex items-center gap-2">
                                @php
                                    $categoryPermissions = $functions->where('category', $category)->filter(function($function) use ($permissions) {
                                        return isset($permissions[$function->code]) && 
                                               ($permissions[$function->code]['view'] || 
                                                $permissions[$function->code]['create'] || 
                                                $permissions[$function->code]['edit'] || 
                                                $permissions[$function->code]['delete']);
                                    })->count();
                                    $totalCategoryFunctions = $functions->where('category', $category)->count();
                                @endphp
                                <span class="text-sm text-slate-600">
                                    {{ $categoryPermissions }}/{{ $totalCategoryFunctions }} có quyền
                                </span>
                                <div class="w-16 bg-slate-200 rounded-full h-1.5">
                                    <div class="bg-gradient-to-r from-blue-500 to-green-500 h-1.5 rounded-full" 
                                         style="width: {{ $totalCategoryFunctions > 0 ? ($categoryPermissions / $totalCategoryFunctions) * 100 : 0 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="hidden" id="category-{{ $category }}">
                        <div class="p-4 space-y-4">
                            @foreach($functions->where('category', $category) as $function)
                                <div class="border rounded-lg p-4 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <h5 class="font-medium text-slate-800">{{ $function->name }}</h5>
                                            @if($function->description)
                                                <p class="text-sm text-slate-500 mt-1">{{ $function->description }}</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @php
                                                $hasPermission = isset($permissions[$function->code]) && 
                                                               ($permissions[$function->code]['view'] || 
                                                                $permissions[$function->code]['create'] || 
                                                                $permissions[$function->code]['edit'] || 
                                                                $permissions[$function->code]['delete']);
                                            @endphp
                                            @if($hasPermission)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check mr-1"></i> Có quyền
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                                    <i class="fas fa-times mr-1"></i> Không có quyền
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <div class="flex items-center p-2 rounded {{ isset($permissions[$function->code]['view']) && $permissions[$function->code]['view'] ? 'bg-blue-50' : 'bg-gray-50' }}">
                                            <i class="fas fa-eye mr-2 {{ isset($permissions[$function->code]['view']) && $permissions[$function->code]['view'] ? 'text-blue-600' : 'text-gray-400' }}"></i>
                                            <span class="text-sm font-medium {{ isset($permissions[$function->code]['view']) && $permissions[$function->code]['view'] ? 'text-blue-800' : 'text-gray-600' }}">
                                                Xem
                                            </span>
                                        </div>
                                        <div class="flex items-center p-2 rounded {{ isset($permissions[$function->code]['create']) && $permissions[$function->code]['create'] ? 'bg-green-50' : 'bg-gray-50' }}">
                                            <i class="fas fa-plus mr-2 {{ isset($permissions[$function->code]['create']) && $permissions[$function->code]['create'] ? 'text-green-600' : 'text-gray-400' }}"></i>
                                            <span class="text-sm font-medium {{ isset($permissions[$function->code]['create']) && $permissions[$function->code]['create'] ? 'text-green-800' : 'text-gray-600' }}">
                                                Tạo
                                            </span>
                                        </div>
                                        <div class="flex items-center p-2 rounded {{ isset($permissions[$function->code]['edit']) && $permissions[$function->code]['edit'] ? 'bg-amber-50' : 'bg-gray-50' }}">
                                            <i class="fas fa-edit mr-2 {{ isset($permissions[$function->code]['edit']) && $permissions[$function->code]['edit'] ? 'text-amber-600' : 'text-gray-400' }}"></i>
                                            <span class="text-sm font-medium {{ isset($permissions[$function->code]['edit']) && $permissions[$function->code]['edit'] ? 'text-amber-800' : 'text-gray-600' }}">
                                                Sửa
                                            </span>
                                        </div>
                                        <div class="flex items-center p-2 rounded {{ isset($permissions[$function->code]['delete']) && $permissions[$function->code]['delete'] ? 'bg-red-50' : 'bg-gray-50' }}">
                                            <i class="fas fa-trash mr-2 {{ isset($permissions[$function->code]['delete']) && $permissions[$function->code]['delete'] ? 'text-red-600' : 'text-gray-400' }}"></i>
                                            <span class="text-sm font-medium {{ isset($permissions[$function->code]['delete']) && $permissions[$function->code]['delete'] ? 'text-red-800' : 'text-gray-600' }}">
                                                Xóa
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Toggle category visibility
function toggleCategory(category) {
    const element = document.getElementById(`category-${category}`);
    const icon = document.getElementById(`icon-${category}`);
    
    if (element.classList.contains('hidden')) {
        element.classList.remove('hidden');
        icon.style.transform = 'rotate(0deg)';
    } else {
        element.classList.add('hidden');
        icon.style.transform = 'rotate(-90deg)';
    }
}

// Toggle all categories
function toggleAllCategories() {
    document.querySelectorAll('[id^="category-"]').forEach(element => {
        element.classList.remove('hidden');
    });
    document.querySelectorAll('[id^="icon-"]').forEach(icon => {
        icon.style.transform = 'rotate(0deg)';
    });
}

// Collapse all categories
function collapseAllCategories() {
    document.querySelectorAll('[id^="category-"]').forEach(element => {
        element.classList.add('hidden');
    });
    document.querySelectorAll('[id^="icon-"]').forEach(icon => {
        icon.style.transform = 'rotate(-90deg)';
    });
}

// Initialize - collapse all categories by default
document.addEventListener('DOMContentLoaded', function() {
    collapseAllCategories();
});
</script>
@endpush

