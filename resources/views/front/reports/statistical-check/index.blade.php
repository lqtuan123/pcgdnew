@extends('layouts.front')

@section('title', 'Kiểm tra thống kê')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Kiểm tra thống kê</h1>
        <p class="text-gray-600">Báo cáo kiểm tra thống kê phổ cập giáo dục và xóa mù chữ</p>
    </div>
    

    {{-- Filter Section --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ url()->current() }}" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-12">
                {{-- Năm học --}}
                <div class="md:col-span-3">
                    <label class="block text-xs text-slate-500 mb-1">Năm học</label>
                    <select name="school_year" class="w-full rounded-lg border px-3 py-2">
                        @php
                            $y = (int)date('Y');
                            $options = [];
                            // Thêm năm học 2025-2026
                            $options[] = '2025-2026';
                            // Tạo danh sách năm học từ năm hiện tại xuống 5 năm
                            for ($i = $y; $i >= $y - 5; $i--) { 
                                $yearOption = ($i-1)."-".$i;
                                if (!in_array($yearOption, $options)) {
                                    $options[] = $yearOption;
                                }
                            }
                            
                            // Mặc định là 2024-2025
                            $defaultYear = '2024-2025';
                            $current = request('school_year') ?? $defaultYear;
                        @endphp
                        @foreach($options as $opt)
                            <option value="{{ $opt }}" {{ $current === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Geo filter: chỉ Tỉnh -> Xã, không trường --}}
                @include('partials.front.geo-filter', [
                    'namePrefix' => 'geo',
                    'showSchool' => false,
                    'showHamlet' => false,
                    'wrapperClass' => 'md:col-span-9 grid gap-3 md:grid-cols-12',
                ])
            </div>

            {{-- Action Buttons --}}
            <div class="flex gap-3 pt-4 border-t">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    📊 Xem báo cáo
                </button>
                {{-- <button type="button" onclick="clearCacheAndReload()" class="px-6 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors">
                    🔄 Làm mới dữ liệu
                </button> --}}
            </div>
        </form>
        
        <script>
        function clearCacheAndReload() {
            if (!confirm('Xóa cache và tải lại dữ liệu mới?\n\nLưu ý: Thao tác này có thể mất vài giây.')) {
                return;
            }
            
            const form = document.querySelector('form');
            const formData = new FormData(form);
            
            fetch('{{ route("front.reports.statistical-check.clear-cache") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    school_year: formData.get('school_year'),
                    scheme: formData.get('scheme'),
                    geo: {
                        province_id: formData.get('geo[province_id]'),
                        ward_id: formData.get('geo[ward_id]')
                    }
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.href = window.location.href;
                } else {
                    alert('Có lỗi: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra. Vui lòng thử lại.');
            });
        }
        </script>
    </div>

    {{-- Report Table --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        @include('front.reports.statistical-check.table')
    </div>
</div>
@endsection

