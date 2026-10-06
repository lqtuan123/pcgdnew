@extends('layouts.admin')

@section('title', 'Chi tiết vai trò')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Chi tiết vai trò: {{ $role->display_name }}</h1>
            <p class="mt-2 text-slate-600">Thông tin chi tiết và phân quyền của vai trò</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.roles.index') }}" class="btn-soft">
                <i class="fas fa-arrow-left mr-2"></i> Quay lại
            </a>
            @can('update', $role)
                <a href="{{ route('admin.roles.edit', $role) }}" class="btn-primary">
                    <i class="fas fa-edit mr-2"></i> Chỉnh sửa
                </a>
            @endcan
        </div>
    </div>

    <!-- Thông tin cơ bản -->
    <div class="grid md:grid-cols-2 gap-6">
        <article class="card">
            <h2 class="text-lg font-semibold mb-4">Thông tin cơ bản</h2>
            <div class="space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-700">ID:</span>
                    <span class="text-slate-600">{{ $role->id }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-700">Tên vai trò:</span>
                    <code class="bg-slate-100 text-slate-800 px-2 py-1 rounded text-sm">{{ $role->name }}</code>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-700">Tên hiển thị:</span>
                    <span class="text-slate-600">{{ $role->display_name }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-700">Mô tả:</span>
                    <span class="text-slate-600">{{ $role->description ?? 'Không có mô tả' }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-700">Cấp độ:</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        Level {{ $role->level }}
                    </span>
                </div>
                <div class="flex justify-between items-center py-2">
                    <span class="font-medium text-slate-700">Loại:</span>
                    @if($role->is_system)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                            System Role
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                            Custom Role
                        </span>
                    @endif
                </div>
            </div>
        </article>

        <article class="card">
            <h2 class="text-lg font-semibold mb-4">Quyền cơ bản</h2>
            <div class="space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-700">Tạo User:</span>
                    @if($role->can_create_users)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                            Có
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                            Không
                        </span>
                    @endif
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-200">
                    <span class="font-medium text-slate-700">Phân quyền:</span>
                    @if($role->can_assign_permissions)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                            Có
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                            Không
                        </span>
                    @endif
                </div>
                <div class="flex justify-between items-center py-2">
                    <span class="font-medium text-slate-700">Quản lý địa lý:</span>
                    @if($role->can_manage_geo_permissions)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                            Có
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800">
                            Không
                        </span>
                    @endif
                </div>
            </div>
        </article>
    </div>

    <!-- Phân quyền chức năng -->
    <article class="card">
        <h2 class="text-lg font-semibold mb-4">Phân quyền chức năng</h2>
        <div class="space-y-4">
            @foreach($categories as $category)
                <div class="border rounded-lg p-4">
                    <h3 class="font-medium text-slate-800 mb-3">{{ $category }}</h3>
                    @php
                        $categoryFunctions = $systemFunctions->where('category', $category);
                    @endphp
                    @if($categoryFunctions->count() > 0)
                        <div class="space-y-3">
                            @foreach($categoryFunctions as $function)
                                @php
                                    $functionPermissions = $permissions[$function->code] ?? [];
                                @endphp
                                <div class="border rounded-lg p-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-slate-800 mb-2">{{ $function->name }}</h4>
                                            <div class="flex gap-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $functionPermissions['view'] ?? false ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600' }}">
                                                    Xem
                                                </span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $functionPermissions['create'] ?? false ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600' }}">
                                                    Tạo
                                                </span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $functionPermissions['edit'] ?? false ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
                                                    Sửa
                                                </span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $functionPermissions['delete'] ?? false ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-600' }}">
                                                    Xóa
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-slate-500 text-sm">Không có chức năng nào trong danh mục này.</p>
                    @endif
                </div>
            @endforeach
        </div>
    </article>
</div>
@endsection
