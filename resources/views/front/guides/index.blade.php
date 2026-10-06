@extends('front.guides.layout')

@section('guide-content')
<div>
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Hướng dẫn sử dụng hệ thống</h1>
        <p class="text-gray-600">Chọn danh mục từ sidebar bên trái để xem hướng dẫn chi tiết cho từng chức năng</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($categories as $category)
        <div class="bg-white rounded-lg border border-gray-200 hover:border-blue-300 transition-colors duration-200">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="flex items-center justify-center w-12 h-12 bg-blue-100 rounded-lg mr-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $category->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $category->functions->count() }} chức năng</p>
                    </div>
                </div>
                
                <div class="space-y-2">
                    @foreach($category->functions->take(3) as $function)
                    <div class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                        {{ $function->name }}
                    </div>
                    @endforeach
                    @if($category->functions->count() > 3)
                    <div class="text-sm text-gray-500">
                        +{{ $category->functions->count() - 3 }} chức năng khác...
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($categories->isEmpty())
    <div class="text-center py-12">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Chưa có hướng dẫn</h3>
        <p class="mt-1 text-sm text-gray-500">Hiện tại chưa có danh mục hướng dẫn nào được tạo.</p>
    </div>
    @endif
</div>
@endsection
