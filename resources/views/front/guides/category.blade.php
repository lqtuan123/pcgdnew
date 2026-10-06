@extends('front.guides.layout')

@section('guide-content')
<div>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $selectedCategory->name }}</h1>
        <p class="text-gray-600">Chọn chức năng để xem hướng dẫn chi tiết</p>
    </div>

    <div class="space-y-4">
        @foreach($functions as $function)
        <div class="bg-white rounded-lg border border-gray-200 hover:border-blue-300 transition-colors duration-200">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-12 h-12 bg-blue-100 rounded-lg mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $function->name }}</h3>
                            <p class="text-sm text-gray-500">{{ $function->contents->count() }} hướng dẫn</p>
                        </div>
                    </div>
                    <a href="{{ route('front.guide.function', $function->id) }}" 
                       class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200">
                        Xem hướng dẫn
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($functions->isEmpty())
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Chưa có chức năng</h3>
        <p class="mt-1 text-sm text-gray-500">Danh mục này chưa có chức năng nào được tạo.</p>
    </div>
    @endif
</div>
@endsection
