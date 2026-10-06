@extends('layouts.admin')

@section('title', 'Quản lý danh mục chức năng')

@section('content')
<div class="space-y-6">
    <!-- Toolbar -->
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Quản lý danh mục chức năng</h1>
        <a href="{{ route('admin.permissions.categories.create') }}" class="btn-primary">
            <i class="fas fa-plus mr-2"></i> Thêm danh mục
        </a>
        <a href="{{ route('admin.permissions.functions') }}" class="btn-soft">
            <i class="fas fa-arrow-left mr-2"></i> Quay lại
        </a>
    </div>

    <!-- Bộ lọc -->
    <article class="card">
        <form method="GET" action="{{ route('admin.permissions.categories') }}" class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-6">
                <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Tên / mã danh mục…"
                    class="w-full rounded-lg border px-3 py-2"
                >
            </div>

            <div class="md:col-span-6 flex items-end gap-2">
                <button type="submit" class="btn-primary">Lọc</button>
                <a href="{{ route('admin.permissions.categories') }}" class="btn-soft">Xoá lọc</a>
            </div>
        </form>
    </article>

    <!-- Bảng danh sách danh mục -->
    <article class="card overflow-hidden">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold">Danh sách danh mục</h2>
            <div class="flex items-center gap-2">
                <div class="text-sm text-slate-500">Tổng: {{ $categories->count() }}</div>
            </div>
        </div>

        <div class="-mx-4 mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th text-left">#</th>
                        <th class="th text-left">Mã danh mục</th>
                        <th class="th text-left">Tên danh mục</th>
                        <th class="th text-left">Màu sắc</th>
                        <th class="th text-left">Thứ tự</th>
                        <th class="th text-left">Trạng thái</th>
                        <th class="th text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($categories as $i => $category)
                        <tr class="hover:bg-slate-50">
                            <td class="td">{{ $i + 1 }}</td>
                            <td class="td">
                                <code class="text-xs bg-slate-100 px-2 py-1 rounded">{{ $category->code }}</code>
                            </td>
                            <td class="td font-medium">{{ $category->name }}</td>
                            <td class="td">
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-4 rounded" style="background-color: {{ $category->color }}"></div>
                                    <span class="text-xs text-slate-500">{{ $category->color }}</span>
                                </div>
                            </td>
                            <td class="td">{{ $category->sort_order }}</td>
                            <td class="td">
                                @if($category->is_active)
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
                                    <a href="{{ route('admin.permissions.categories.edit', $category) }}" class="text-amber-600 hover:underline text-sm">
                                        Sửa
                                    </a>
                                    <form method="POST" action="{{ route('admin.permissions.categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này?')">
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
                        <tr><td class="td" colspan="7">Không có dữ liệu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>
@endsection























































