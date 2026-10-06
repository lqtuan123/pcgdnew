@extends('layouts.admin')
@section('title','Chi tiết chức năng - ' . $function->name)
@section('crumb','Cài đặt / Hướng dẫn / ' . $category->name . ' / Chi tiết')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.settings.guides.functions.index', $category->id) }}" class="btn-soft">← Quay lại</a>
        <a href="{{ route('admin.settings.guides.functions.edit', [$category->id, $function->id]) }}" class="btn-primary">Sửa</a>
        <div class="ml-auto"></div>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <article class="card">
            <h3 class="font-semibold text-lg mb-4">Thông tin chức năng</h3>
            <div class="space-y-3">
                <div>
                    <span class="text-xs text-slate-500">ID:</span>
                    <div class="font-mono text-sm">{{ $function->id }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Tên chức năng:</span>
                    <div class="font-medium">{{ $function->name }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Danh mục:</span>
                    <div>{{ $category->name }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Ngày tạo:</span>
                    <div class="text-sm">{{ $function->created_at->format('d/m/Y H:i:s') }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Ngày cập nhật:</span>
                    <div class="text-sm">{{ $function->updated_at->format('d/m/Y H:i:s') }}</div>
                </div>
            </div>
        </article>

        <article class="card">
            <h3 class="font-semibold text-lg mb-4">Danh sách nội dung hướng dẫn ({{ $function->contents->count() }})</h3>
            @if($function->contents->count() > 0)
                <div class="space-y-2">
                    @foreach($function->contents as $content)
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                        <div>
                            <div class="font-medium">{{ $content->title }}</div>
                            <div class="text-xs text-slate-500">
                                @if($content->video_url)
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700">Có video</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Không có video</span>
                                @endif
                                <span class="mx-2">•</span>
                                {{ $content->created_at->format('d/m/Y') }}
                            </div>
                        </div>
                        <a href="{{ route('admin.settings.guides.functions.contents.show', [$category->id, $function->id, $content->id]) }}" 
                           class="text-blue-600 hover:underline text-sm">
                            Xem
                        </a>
                    </div>
                    @endforeach
                </div>
            @else
                <p class="text-slate-500 text-sm">Chưa có nội dung hướng dẫn nào</p>
            @endif
        </article>
    </div>
</div>
@endsection
