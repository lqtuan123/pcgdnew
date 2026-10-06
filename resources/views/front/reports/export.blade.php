@extends('layouts.front')

@section('title', 'Xuất báo cáo')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Xuất báo cáo</h1>
            <p class="mt-2 text-slate-600">Xuất báo cáo dưới định dạng Excel hoặc PDF</p>
        </div>
    </div>

    <!-- Thông báo -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center">
            <i class="fas fa-info-circle text-blue-600 mr-3"></i>
            <div>
                <h3 class="font-semibold text-blue-800">Chức năng đang phát triển</h3>
                <p class="text-blue-700">Trang xuất báo cáo đang được xây dựng. Vui lòng quay lại sau.</p>
            </div>
        </div>
    </div>

    <!-- Nội dung tạm thời -->
    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <div class="text-center py-12">
            <i class="fas fa-download text-6xl text-slate-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-slate-600 mb-2">Xuất báo cáo</h3>
            <p class="text-slate-500 mb-6">Trang này sẽ cho phép xuất báo cáo dưới nhiều định dạng khác nhau</p>
            
            <div class="flex items-center justify-center gap-4">
                <a href="{{ route('front.reports.summary') }}" class="btn-primary">
                    <i class="fas fa-file-alt mr-2"></i> Báo cáo tổng hợp
                </a>
                <a href="{{ route('front.stats.charts') }}" class="btn-soft">
                    <i class="fas fa-chart-bar mr-2"></i> Biểu đồ
                </a>
            </div>
        </div>
    </div>
    </div>
</div>
@endsection
