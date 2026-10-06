@extends('layouts.admin')
@section('title','Chi tiết danh mục hướng dẫn')
@section('crumb','Cài đặt / Hướng dẫn / Chi tiết')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.settings.guides.index') }}" class="btn-soft">← Quay lại</a>
        <a href="{{ route('admin.settings.guides.edit', $category->id) }}" class="btn-primary">Sửa</a>
        <div class="ml-auto"></div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <article class="card">
            <h3 class="font-semibold text-lg mb-4">Thông tin danh mục</h3>
            <div class="space-y-3">
                <div>
                    <span class="text-xs text-slate-500">ID:</span>
                    <div class="font-mono text-sm">{{ $category->id }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Tên danh mục:</span>
                    <div class="font-medium">{{ $category->name }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Loại người dùng:</span>
                    <div>
                        @if($category->role_type == 'admin')
                            <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">Admin</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">User</span>
                        @endif
                    </div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Ngày tạo:</span>
                    <div class="text-sm">{{ $category->created_at->format('d/m/Y H:i:s') }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Ngày cập nhật:</span>
                    <div class="text-sm">{{ $category->updated_at->format('d/m/Y H:i:s') }}</div>
                </div>
            </div>
        </article>

        <article class="card">
            <h3 class="font-semibold text-lg mb-4">Danh sách chức năng ({{ $category->functions->count() }})</h3>
            @if($category->functions->count() > 0)
                <div class="space-y-2">
                    @foreach($category->functions as $function)
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                        <div>
                            <div class="font-medium">{{ $function->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $function->contents->count() }} nội dung hướng dẫn
                            </div>
                        </div>
                        <a href="{{ route('admin.settings.guides.functions.show', [$category->id, $function->id]) }}" 
                           class="text-blue-600 hover:underline text-sm">
                            Xem
                        </a>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500 text-sm">Chưa có chức năng nào</p>
            @endif
        </article>
    </div>
</div>
@endsection
