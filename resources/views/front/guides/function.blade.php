@extends('front.guides.layout')

@section('guide-content')
<div>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $selectedFunction->name }}</h1>
        <p class="text-gray-600">Hướng dẫn chi tiết sử dụng chức năng</p>
    </div>

    @if($contents->isNotEmpty())
        @foreach($contents as $index => $content)
        <div class="bg-white rounded-lg border border-gray-200 mb-6">
            <div class="p-6">
                {{-- Header --}}
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $content->title }}</h2>
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
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                        </svg>
                        Video hướng dẫn
                    </h3>
                    <div class="bg-gray-100 rounded-lg p-4">
                        @php
                            $embedUrl = \App\Helpers\YouTubeHelper::getEmbedUrl($content->video_url);
                        @endphp
                        @if($embedUrl)
                            <iframe 
                                src="{{ $embedUrl }}" 
                                title="Video hướng dẫn"
                                class="w-full h-64 rounded-lg"
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
                                referrerpolicy="strict-origin-when-cross-origin"
                                allowfullscreen>
                            </iframe>
                        @else
                            <div class="text-center py-8 text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                <p>Link video không hợp lệ</p>
                                <p class="text-sm">{{ $content->video_url }}</p>
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Content section --}}
                <div class="prose prose-lg max-w-none">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Nội dung chi tiết
                    </h3>
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                        {!! $content->content !!}
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    @else
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Chưa có hướng dẫn</h3>
        <p class="mt-1 text-sm text-gray-500">Chức năng này chưa có hướng dẫn nào được tạo.</p>
    </div>
    @endif
</div>
@endsection
