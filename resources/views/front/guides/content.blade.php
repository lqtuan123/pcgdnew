@extends('front.guides.layout')

@section('guide-content')
<div class="max-w-6xl">
    {{-- Action Links --}}
    <div class="mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="#" class="text-blue-600 hover:text-blue-800 underline text-sm">Thêm mới phiếu điều tra</a>
            <span class="text-gray-400">|</span>
            <a href="#" class="text-blue-600 hover:text-blue-800 underline text-sm">Thêm mới đối tượng điều tra</a>
            <span class="text-gray-400">|</span>
            <a href="#" class="text-blue-600 hover:text-blue-800 underline text-sm">Chỉnh sửa phiếu điều tra</a>
            <span class="text-gray-400">|</span>
            <a href="#" class="text-blue-600 hover:text-blue-800 underline text-sm">Xóa phiếu điều tra</a>
            <span class="text-gray-400">|</span>
            <a href="#" class="text-blue-600 hover:text-blue-800 underline text-sm">In phiếu điều tra</a>
            <span class="text-gray-400">|</span>
            <a href="#" class="text-blue-600 hover:text-blue-800 underline text-sm">Chuyển phiếu từ thôn/tổ/xóm này qua thôn/tổ/xóm khác</a>
            <span class="text-gray-400">|</span>
            <a href="#" class="text-blue-600 hover:text-blue-800 underline text-sm">Tách hộ</a>
            <span class="text-gray-400">|</span>
            <a href="#" class="text-blue-600 hover:text-blue-800 underline text-sm">Ghép hộ</a>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="bg-white rounded-lg border border-gray-200">
        <div class="p-6">
            {{-- Header --}}
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $content->title }}</h1>
                <div class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Cập nhật lần cuối: {{ $content->updated_at->format('d/m/Y H:i') }}
                </div>
            </div>

            {{-- Video section --}}
            @if($content->video_url)
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                    </svg>
                    Video hướng dẫn
                </h2>
                <div class="bg-gray-100 rounded-lg p-4">
                    <iframe 
                        src="{{ $content->video_url }}" 
                        title="Video hướng dẫn"
                        class="w-full h-64 rounded-lg"
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
            @endif

            {{-- Content section --}}
            <div class="prose prose-lg max-w-none">
                <h2 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Nội dung chi tiết
                </h2>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    {!! $content->content !!}
                </div>
            </div>

            {{-- Navigation --}}
            <div class="mt-6 pt-4 border-t border-gray-200">
                <div class="flex justify-between">
                    <a href="{{ route('front.guide.function', $content->function->id) }}" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Quay lại
                    </a>
                    
                    <a href="{{ route('front.guide.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Trang chủ hướng dẫn
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
