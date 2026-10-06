@extends('layouts.admin')
@section('title','Danh mục hướng dẫn')
@section('crumb','Cài đặt / Hướng dẫn')

@section('content')
@if(session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 p-4 text-emerald-700">
        {{ session('success') }}
    </div>
@endif

<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.settings.guides.create') }}" class="btn-primary">+ Thêm danh mục</a>
        <div class="ml-auto"></div>
    </div>

    <article class="card">
        <div class="-mx-4 mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th text-left">#</th>
                        <th class="th text-left">Tên danh mục</th>
                        <th class="th text-left">Loại người dùng</th>
                        <th class="th text-left">Số chức năng</th>
                        <th class="th text-left">Ngày tạo</th>
                        <th class="th text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($categories as $i => $category)
                    <tr class="hover:bg-slate-50">
                        <td class="td">{{ $i + 1 }}</td>
                        <td class="td font-medium">{{ $category->name }}</td>
                        <td class="td">
                            @if($category->role_type == 'admin')
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">Admin</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700">User</span>
                            @endif
                        </td>
                        <td class="td">{{ $category->functions->count() }}</td>
                        <td class="td">{{ $category->created_at->format('d/m/Y H:i') }}</td>
                        <td class="td text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.settings.guides.functions.index', $category->id) }}" 
                                   class="text-blue-600 hover:underline" title="Xem chức năng">Chức năng</a>
                                <a href="{{ route('admin.settings.guides.show', $category->id) }}" 
                                   class="text-slate-600 hover:underline" title="Xem chi tiết">Xem</a>
                                <a href="{{ route('admin.settings.guides.edit', $category->id) }}" 
                                   class="text-amber-600 hover:underline" title="Sửa">Sửa</a>
                                <button type="button" class="text-rose-600 hover:underline"
                                        x-data
                                        @click="$dispatch('confirm-delete', {action: '{{ route('admin.settings.guides.destroy', $category->id) }}', name: @js($category->name)})">
                                    Xóa
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td class="td text-center" colspan="6">Không có danh mục nào</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </article>
</div>

{{-- Modal confirm xóa --}}
<div x-data="{ open:false, action:'', name:'' }"
     @confirm-delete.window="open=true; action=$event.detail.action; name=$event.detail.name">
    <div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
        <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
        <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
            <h3 class="font-semibold text-lg">Xóa danh mục?</h3>
            <p class="mt-2 text-sm text-slate-600">Bạn chắc chắn muốn xóa <span class="font-medium" x-text="name"></span>?</p>
            <form :action="action" method="POST" class="mt-4 flex justify-end gap-2">
                @csrf @method('DELETE')
                <button type="button" class="btn-soft" @click="open=false">Hủy</button>
                <button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 text-white">Xóa</button>
            </form>
        </div>
    </div>
</div>
@endsection
