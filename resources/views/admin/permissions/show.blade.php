@extends('layouts.admin')

@section('title', 'Chi tiết Quyền')
@section('crumb', 'Chi tiết Quyền')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Chi tiết Quyền</h1>
        <p class="mt-2 text-gray-600 dark:text-gray-400">Thông tin chi tiết về quyền: {{ $permission->display_name }}</p>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Thông tin quyền -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">Thông tin quyền</h2>
                        <div class="flex space-x-2">
                            <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn-soft">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Chỉnh sửa
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tên quyền</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ $permission->name }}</code>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Tên hiển thị</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $permission->display_name }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Module</dt>
                            <dd class="mt-1">
                                <span class="badge badge-info">{{ $permission->module }}</span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Action</dt>
                            <dd class="mt-1">
                                <span class="badge badge-success">{{ $permission->action }}</span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Trạng thái</dt>
                            <dd class="mt-1">
                                @if($permission->is_active)
                                    <span class="badge badge-success">Hoạt động</span>
                                @else
                                    <span class="badge badge-danger">Tạm dừng</span>
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Ngày tạo</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $permission->created_at->format('d/m/Y H:i') }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Mô tả</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                {{ $permission->description ?: 'Không có mô tả' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Vai trò sử dụng quyền -->
        <div>
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Vai trò sử dụng</h3>
                </div>

                <div class="p-6">
                    @if($roles->count() > 0)
                        <div class="space-y-3">
                            @foreach($roles as $role)
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $role->display_name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $role->users->count() }} người dùng</div>
                                    </div>
                                    <div class="text-right">
                                        <span class="badge badge-primary">Cấp {{ $role->level }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-6">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Chưa có vai trò nào</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Quyền này chưa được gán cho vai trò nào.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex items-center justify-between">
        <a href="{{ route('admin.permissions.index') }}" class="btn-soft">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Quay lại danh sách
        </a>

        <div class="flex space-x-3">
            <a href="{{ route('admin.permissions.edit', $permission) }}" class="btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Chỉnh sửa
            </a>
            
            <form action="{{ route('admin.permissions.destroy', $permission) }}" method="POST" class="inline" onsubmit="return confirm('Bạn có chắc muốn xóa quyền này? Hành động này không thể hoàn tác.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-danger">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Xóa quyền
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
