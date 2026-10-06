@extends('layouts.admin')
@section('title','Chi tiết nội dung hướng dẫn - ' . $content->title)
@section('crumb','Cài đặt / Hướng dẫn / ' . $category->name . ' / ' . $function->name . ' / Chi tiết')

@section('content')
<div class="space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.settings.guides.functions.contents.index', [$category->id, $function->id]) }}" class="btn-soft">← Quay lại</a>
        <a href="{{ route('admin.settings.guides.functions.contents.edit', [$category->id, $function->id, $content->id]) }}" class="btn-primary">Sửa</a>
        <div class="ml-auto"></div>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <article class="card md:col-span-1">
            <h3 class="font-semibold text-lg mb-4">Thông tin nội dung</h3>
            <div class="space-y-3">
                <div>
                    <span class="text-xs text-slate-500">ID:</span>
                    <div class="font-mono text-sm">{{ $content->id }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Tiêu đề:</span>
                    <div class="font-medium">{{ $content->title }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Danh mục:</span>
                    <div>{{ $category->name }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Chức năng:</span>
                    <div>{{ $function->name }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Video:</span>
                    <div>
                        @if($content->video_url)
                            @php
                                $embedUrl = \App\Helpers\YouTubeHelper::getEmbedUrl($content->video_url);
                            @endphp
                            @if($embedUrl)
                                <a href="{{ $content->video_url }}" target="_blank" class="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 hover:bg-green-100">
                                    Xem video
                                </a>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">Link không hợp lệ</span>
                            @endif
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Không có</span>
                        @endif
                    </div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Ngày tạo:</span>
                    <div class="text-sm">{{ $content->created_at->format('d/m/Y H:i:s') }}</div>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Ngày cập nhật:</span>
                    <div class="text-sm">{{ $content->updated_at->format('d/m/Y H:i:s') }}</div>
                </div>
            </div>
        </article>

        <article class="card md:col-span-2">
            <h3 class="font-semibold text-lg mb-4">Nội dung hướng dẫn</h3>
            <div class="prose max-w-none">
                {!! $content->content !!}
            </div>
        </article>
    </div>
</div>
@endsection