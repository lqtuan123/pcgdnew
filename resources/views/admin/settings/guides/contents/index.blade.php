@extends('layouts.admin')
@section('title','Nội dung hướng dẫn - ' . $function->name)
@section('crumb','Cài đặt / Hướng dẫn / ' . $category->name . ' / ' . $function->name)

@section('content')
@if(session('success'))
    <div class="mb-4 rounded-lg bg-emerald-50 p-4 text-emerald-700">
        {{ session('success') }}
    </div>
@endif

<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.settings.guides.functions.index', $category->id) }}" class="btn-soft">← Quay lại chức năng</a>
        <a href="{{ route('admin.settings.guides.functions.contents.create', [$category->id, $function->id]) }}" class="btn-primary">+ Thêm nội dung</a>
        <div class="ml-auto"></div>
    </div>

    <div class="rounded-lg bg-slate-50 p-4">
        <div class="text-sm text-slate-600">
            <strong>Danh mục:</strong> {{ $category->name }} 
            <span class="mx-2">•</span>
            <strong>Chức năng:</strong> {{ $function->name }}
        </div>
    </div>

    <article class="card">
        <div class="-mx-4 mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="th text-left">#</th>
                        <th class="th text-left">Tiêu đề</th>
                        <th class="th text-left">Video</th>
                        <th class="th text-left">Ngày tạo</th>
                        <th class="th text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($contents as $i => $content)
                    <tr class="hover:bg-slate-50">
                        <td class="td">{{ $i + 1 }}</td>
                        <td class="td font-medium">{{ $content->title }}</td>
                        <td class="td">
                            @if($content->video_url)
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700">Có video</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Không có</span>
                            @endif
                        </td>
                        <td class="td">{{ $content->created_at->format('d/m/Y H:i') }}</td>
                        <td class="td text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.settings.guides.functions.contents.show', [$category->id, $function->id, $content->id]) }}" 
                                   class="text-slate-600 hover:underline" title="Xem chi tiết">Xem</a>
                                <a href="{{ route('admin.settings.guides.functions.contents.edit', [$category->id, $function->id, $content->id]) }}" 
                                   class="text-amber-600 hover:underline" title="Sửa">Sửa</a>
                                <button type="button" class="text-rose-600 hover:underline"
                                        x-data
                                        @click="$dispatch('confirm-delete', {action: '{{ route('admin.settings.guides.functions.contents.destroy', [$category->id, $function->id, $content->id]) }}', name: @js($content->title)})">
                                    Xóa
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td class="td text-center" colspan="5">Không có nội dung nào</td></tr>
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
            <h3 class="font-semibold text-lg">Xóa nội dung?</h3>
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