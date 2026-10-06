@extends('layouts.front')

@section('title', 'So dò thống kê')

@section('content')
<div class="container mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">So dò thống kê</h1>
        <p class="text-gray-600">Báo cáo so dò thống kê phổ cập giáo dục và xóa mù chữ</p>
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

                {{-- Geo filter: chỉ Tỉnh -> Xã -> Thôn/Xóm, không trường --}}
                @include('partials.front.geo-filter', [
                    'namePrefix' => 'geo',
                    'showSchool' => false,
                    'showHamlet' => true,
                    'wrapperClass' => 'md:col-span-9 grid gap-3 md:grid-cols-12',
                ])
            </div>

            {{-- Action Buttons --}}
            <div class="flex gap-3 pt-4 border-t">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    📊 Xem báo cáo
                </button>
                <button type="button" onclick="clearCacheAndReload()" class="px-6 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors">
                    🔄 Làm mới dữ liệu
                </button>
            </div>
        </form>
        
        <script>
        function clearCacheAndReload() {
            if (!confirm('Xóa cache và tải lại dữ liệu mới?\n\nLưu ý: Thao tác này có thể mất vài giây.')) {
                return;
            }
            
            // Lấy parameters hiện tại từ form
            const form = document.querySelector('form');
            const formData = new FormData(form);
            
            // Gọi API clear cache
            fetch('{{ route("front.reports.statistical-verification.clear-cache") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin', // Đảm bảo cookies (session) được gửi kèm
                body: JSON.stringify({
                    school_year: formData.get('school_year'),
                    scheme: formData.get('scheme'),
                    geo: {
                        province_id: formData.get('geo[province_id]'),
                        ward_id: formData.get('geo[ward_id]'),
                        hamlet_id: formData.get('geo[hamlet_id]')
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
                    // Reload trang bằng cách redirect đến URL hiện tại (giữ nguyên params)
                    // KHÔNG dùng form.submit() vì có thể gây mất session
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

    {{-- Tabs Section --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200" x-data="{ activeTab: 'universal_education' }">
        {{-- Tab Headers --}}
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button 
                    @click="activeTab = 'universal_education'"
                    :class="activeTab === 'universal_education' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="w-1/2 py-4 px-6 text-center border-b-2 font-medium text-sm transition-colors">
                    Phổ cập giáo dục
                </button>
                <button 
                    @click="activeTab = 'literacy'"
                    :class="activeTab === 'literacy' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="w-1/2 py-4 px-6 text-center border-b-2 font-medium text-sm transition-colors">
                    Xóa mù chữ
                </button>
            </nav>
        </div>

        {{-- Tab Contents --}}
        <div class="p-6">
            {{-- Tab 1: Phổ cập giáo dục --}}
            <div x-show="activeTab === 'universal_education'" x-cloak>
                @include('front.reports.statistical-verification.universal-education-table')
            </div>

            {{-- Tab 2: Xóa mù chữ --}}
            <div x-show="activeTab === 'literacy'" x-cloak>
                @include('front.reports.statistical-verification.literacy-table')
            </div>
        </div>
    </div>
</div>
@endsection

