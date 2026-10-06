@extends('layouts.front')
@section('title', 'Chỉ tiêu PCGD')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    @if($isWardLevel && $ward)
    <!-- Nút toggle vùng khó khăn - chỉ hiển thị với tài khoản cấp xã -->
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div>
                    <h3 class="font-semibold text-gray-900">{{ $ward->name }}</h3>
                    <p class="text-sm text-gray-600 mt-0.5">
                        Trạng thái hiện tại: 
                        <span class="font-medium" id="status-text">
                            @if($ward->is_difficult_area)
                                <span class="text-amber-700">Vùng khó khăn/ĐBKK</span>
                            @else
                                <span class="text-green-700">Vùng không khó khăn/ĐBKK</span>
                            @endif
                        </span>
                    </p>
                </div>
            </div>
            <button type="button" 
                    id="toggle-area-btn"
                    class="px-4 py-2 rounded-lg font-medium transition-colors"
                    x-data="{ isDifficult: {{ $ward->is_difficult_area ? 'true' : 'false' }} }"
                    :class="isDifficult ? 'bg-green-600 hover:bg-green-700 text-white' : 'bg-amber-600 hover:bg-amber-700 text-white'"
                    @click="toggleDifficultArea()">
                <span x-show="!isDifficult">Chuyển sang vùng KK/ĐBKK</span>
                <span x-show="isDifficult">Chuyển sang vùng không KK/ĐBKK</span>
            </button>
        </div>
    </div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">Chỉ tiêu PCGD</h1>
                    <p class="mt-1 text-sm text-gray-600">Xem thông tin chỉ tiêu các chương trình Phổ cập Giáo dục</p>
                </div>
            </div>

            {{-- Form chọn chương trình --}}
            <form method="GET" action="{{ route('front.pcgd-chi-tieu.index') }}" class="mt-4">
                <div class="flex gap-3">
                    <div class="flex-1 max-w-md">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Chọn chương trình</label>
                        <select name="chuong_trinh_id" 
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                onchange="this.form.submit()">
                            @foreach($chuongTrinhs as $ct)
                                <option value="{{ $ct->id }}" {{ $selectedChuongTrinh && $selectedChuongTrinh->id == $ct->id ? 'selected' : '' }}>
                                    {{ $ct->ten_chuong_trinh }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>

        @if($selectedChuongTrinh)
        <div class="p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-gray-800">{{ $selectedChuongTrinh->ten_chuong_trinh }}</h2>
                @if($selectedChuongTrinh->mo_ta)
                    <p class="text-sm text-gray-600 mt-1">{{ $selectedChuongTrinh->mo_ta }}</p>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                STT
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tên chỉ tiêu
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Không KK/ĐBKK
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                Vùng KK/ĐBKK
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($chiTieus as $index => $chiTieu)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $chiTieu->ten_chi_tieu }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Mã: {{ $chiTieu->ma_chi_tieu }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($chiTieu->khong_kk_dbkk)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-800">
                                        @formatNumber($chiTieu->khong_kk_dbkk, 2)%
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($chiTieu->vung_kk_dbkk)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-green-100 text-green-800">
                                        @formatNumber($chiTieu->vung_kk_dbkk, 2)%
                                    </span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="font-medium">Chương trình này chưa có chỉ tiêu nào</p>
                                    <p class="text-xs mt-1">Vui lòng liên hệ quản trị viên để thêm chỉ tiêu</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</div>

@if($isWardLevel && $ward)
<script>
function toggleDifficultArea() {
    const btn = document.getElementById('toggle-area-btn');
    const statusText = document.getElementById('status-text');
    
    // Disable button during request
    btn.disabled = true;
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    
    fetch('{{ route("front.pcgd-chi-tieu.toggle") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hiển thị thông báo thành công
            const alert = document.createElement('div');
            alert.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in';
            alert.textContent = data.message;
            document.body.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 3000);
            
            // Reload trang để cập nhật UI
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            alert('Có lỗi xảy ra: ' + data.message);
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi cập nhật trạng thái vùng');
        btn.disabled = false;
        btn.classList.remove('opacity-50', 'cursor-not-allowed');
    });
}
</script>
@endif
@endsection
