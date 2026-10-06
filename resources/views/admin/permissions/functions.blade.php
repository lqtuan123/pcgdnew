@extends('layouts.admin')

@section('title', 'Quản lý chức năng hệ thống')

@section('content')
<div class="space-y-6">
    <!-- Toolbar -->
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Quản lý chức năng hệ thống</h1>
        <a href="{{ route('admin.permissions.functions.create') }}" class="btn-primary">
            <i class="fas fa-plus mr-2"></i> Thêm chức năng
        </a>
        <a href="{{ route('admin.permissions.categories') }}" class="btn-soft">
            <i class="fas fa-tags mr-2"></i> Quản lý danh mục
        </a>
        <a href="{{ route('admin.permissions.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left mr-2"></i> Quay lại
        </a>
    </div>

    <!-- Bộ lọc -->
    <article class="card">
        <form method="GET" action="{{ route('admin.permissions.functions') }}" class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-4">
                <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Tên / mã chức năng…"
                    class="w-full rounded-lg border px-3 py-2"
                >
            </div>

            <div class="md:col-span-4">
                <label class="block text-xs text-slate-500 mb-1">Danh mục</label>
                <select name="category" class="w-full rounded-lg border px-3 py-2">
                    <option value="">— Tất cả —</option>
                    @foreach(\App\Models\FunctionCategory::active()->ordered()->get() as $category)
                        <option value="{{ $category->code }}" @selected(request('category') == $category->code)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-4 flex items-end gap-2">
                <button type="submit" class="btn-primary">Lọc</button>
                <a href="{{ route('admin.permissions.functions') }}" class="btn-soft">Xoá lọc</a>
            </div>
        </form>
    </article>

    <!-- Bảng danh sách chức năng -->
    <article class="card overflow-hidden">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Danh sách chức năng</h2>
            <div class="flex items-center gap-2">
                <div class="text-sm text-slate-500">Tổng: {{ $functions->count() }}</div>
            </div>
        </div>

        <div class="-mx-4 mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th text-left">#</th>
                        <th class="th text-left">Mã chức năng</th>
                        <th class="th text-left">Tên chức năng</th>
                        <th class="th text-left">Danh mục</th>
                        <th class="th text-left">Trạng thái</th>
                        <th class="th text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($functions as $i => $function)
                        <tr class="hover:bg-slate-50">
                            <td class="td">{{ $i + 1 }}</td>
                            <td class="td">
                                <code class="text-xs bg-slate-100 px-2 py-1 rounded">{{ $function->code }}</code>
                            </td>
                            <td class="td font-medium">{{ $function->name }}</td>
                            <td class="td">
                                @php
                                    $category = \App\Models\FunctionCategory::where('code', $function->category)->first();
                                @endphp
                                @if($category)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background-color: {{ $category->color }}20; color: {{ $category->color }}; border: 1px solid {{ $category->color }}40;">
                                        {{ $category->name }}
                                    </span>
                                @else
                                    <span class="badge badge-primary">{{ ucfirst($function->category) }}</span>
                                @endif
                            </td>
                            <td class="td">
                                @if($function->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Hoạt động
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                        Không hoạt động
                                    </span>
                                @endif
                            </td>
                            <td class="td text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.permissions.functions.edit', $function) }}" class="text-amber-600 hover:underline text-sm">
                                        Sửa
                                    </a>
                                    <form method="POST" action="{{ route('admin.permissions.functions.destroy', $function) }}" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa chức năng này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-rose-600 hover:underline text-sm">
                                            Xóa
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="td" colspan="6">Không có dữ liệu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>
@endsection