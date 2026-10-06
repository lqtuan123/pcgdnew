@extends('layouts.front')
@section('title', 'Chi tiết báo cáo tài chính GDMN')
@section('crumb', 'Chi tiết báo cáo tài chính GDMN')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Chi tiết báo cáo tài chính GDMN</h1>
        <p class="mt-1 text-sm text-gray-600">
            Thông tin chi tiết về báo cáo tài chính chi cho giáo dục mầm non
        </p>
    </div>

    <!-- Details Card -->
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <div class="grid gap-6 md:grid-cols-2">
            <!-- Năm -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Năm</label>
                <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->year }}</p>
            </div>

            <!-- Xã/Phường -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tỉnh/Thành phố</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->province->name ?? 'N/A' }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Xã/Phường</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->ward->name ?? 'N/A' }}</p>
                </div>

            <!-- Mục chi chính -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Mục chi chính</label>
                <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->main_name }}</p>
            </div>

            <!-- Tiểu mục -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Tiểu mục</label>
                <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->sub_name ?? '-' }}</p>
            </div>

            <!-- Số tiền -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Số tiền</label>
                <p class="mt-1 text-sm text-gray-900">
                    <span title="{{ \App\Helpers\NumberToWordsHelper::convertCurrency($gdmnFinance->amount, $gdmnFinance->unit === 'Tr.đồng') }}" 
                          class="cursor-help border-b border-dotted border-gray-400 hover:border-blue-500 hover:text-blue-600 transition-colors">
                        {{ number_format($gdmnFinance->amount, 0, ',', '.') }}
                    </span>
                </p>
            </div>

            <!-- Đơn vị tính -->
            <div>
                <label class="block text-sm font-medium text-gray-700">Đơn vị tính</label>
                <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->unit }}</p>
            </div>
        </div>

        <!-- Ghi chú -->
        @if($gdmnFinance->note)
            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700">Ghi chú</label>
                <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->note }}</p>
            </div>
        @endif

        <!-- Thông tin thời gian -->
        <div class="mt-6 border-t border-gray-200 pt-6">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ngày tạo</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->created_at->format('d/m/Y H:i') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ngày cập nhật</label>
                    <p class="mt-1 text-sm text-gray-900">{{ $gdmnFinance->updated_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mt-8 flex justify-end space-x-3">
            <a href="{{ route('gdmn-finance.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Quay lại
            </a>
            <a href="{{ route('gdmn-finance.edit', $gdmnFinance) }}" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                Chỉnh sửa
            </a>
        </div>
    </div>
</div>
@endsection
