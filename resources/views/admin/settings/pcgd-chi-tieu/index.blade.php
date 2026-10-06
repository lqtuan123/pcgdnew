@extends('layouts.admin')
@section('title', 'Quản lý giá trị chỉ tiêu')
@section('crumb', 'Cài đặt / Giá trị chỉ tiêu')

@section('content')
    <style>
        [x-cloak] {
            display: none !important
        }
    </style>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <h2 class="text-xl font-semibold">Quản lý hệ thống chỉ tiêu</h2>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ session('error') }}
            </div>
        @endif

        {{-- Các nút quản lý --}}
        <article class="card">
            <h3 class="text-lg font-semibold mb-4">🛠️ Công cụ quản lý</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Quản lý chương trình --}}
                <a href="{{ route('admin.settings.chuong-trinh.index') }}" 
                   class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 border-2 border-blue-200 rounded-lg transition group">
                    <svg class="w-12 h-12 text-blue-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h4 class="font-semibold text-gray-800 group-hover:text-blue-600">Chương trình</h4>
                    <p class="text-xs text-gray-600 text-center mt-1">PCGD Mức 1/2/3, XMC Mức 1/2...</p>
                </a>

                {{-- Quản lý chỉ tiêu --}}
                <a href="{{ route('admin.settings.chi-tieu.index') }}" 
                   class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 border-2 border-green-200 rounded-lg transition group">
                    <svg class="w-12 h-12 text-green-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    <h4 class="font-semibold text-gray-800 group-hover:text-green-600">Chỉ tiêu</h4>
                    <p class="text-xs text-gray-600 text-center mt-1">Danh mục chỉ tiêu</p>
                </a>
            </div>
        </article>

        {{-- Form chọn chương trình --}}
        <article class="card">
            <h3 class="text-lg font-semibold mb-4">📋 Nhập giá trị chỉ tiêu</h3>
            <form method="GET" action="{{ route('admin.settings.pcgd-chi-tieu.index') }}" class="space-y-4">
                <div class="grid md:grid-cols-4 gap-4">
                    {{-- Chọn chương trình --}}
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Chương trình <span class="text-rose-600">*</span></label>
                        <select name="chuong_trinh_id" 
                                class="input w-full"
                                required>
                            <option value="">-- Chọn chương trình --</option>
                            @foreach($chuongTrinhs as $ct)
                                <option value="{{ $ct->id }}" {{ request('chuong_trinh_id') == $ct->id ? 'selected' : '' }}>
                                    {{ $ct->ten_chuong_trinh }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Nút xem --}}
                    <div class="flex items-end">
                        <button type="submit" class="btn-primary w-full">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Xem chỉ tiêu
                        </button>
                    </div>
                </div>
            </form>
        </article>

        {{-- Bảng chỉ tiêu --}}
        @if($selectedChuongTrinh && $chiTieuGiaTris->isNotEmpty())
            <article class="card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">
                        {{ $selectedChuongTrinh->ten_chuong_trinh }}
                    </h3>
                </div>

                <form method="POST" action="{{ route('admin.settings.pcgd-chi-tieu.update') }}">
                    @csrf
                    <input type="hidden" name="chuong_trinh_id" value="{{ $selectedChuongTrinh->id }}">

                    <div class="sm:hidden text-xs text-slate-500 mt-1 px-1 mb-3">Kéo ngang để xem thêm →</div>
                    <div class="-mx-4 overflow-x-auto overflow-y-visible" style="-webkit-overflow-scrolling: touch;">
                        <table class="min-w-[600px] sm:min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="th text-left" style="min-width: 300px;">Chỉ tiêu</th>
                                    <th class="th text-center whitespace-nowrap" style="width: 150px;">Không KK/ĐBKK (%)</th>
                                    <th class="th text-center whitespace-nowrap" style="width: 150px;">Vùng KK/ĐBKK (%)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($chiTieuGiaTris as $index => $item)
                                    <tr class="hover:bg-slate-50">
                                        <td class="td">
                                            <div class="font-medium">{{ $item->chiTieu->ten_chi_tieu }}</div>
                                            <div class="text-xs text-slate-500 mt-1">{{ $item->chiTieu->ma_chi_tieu }}</div>
                                            <input type="hidden" name="chi_tieus[{{ $index }}][chi_tieu_id]" value="{{ $item->chi_tieu_id }}">
                                        </td>
                                        <td class="td text-center">
                                            <input type="text" 
                                                   name="chi_tieus[{{ $index }}][khong_kk_dbkk]" 
                                                   value="{{ $item->khong_kk_dbkk ? \App\Helpers\NumberFormatHelper::format($item->khong_kk_dbkk, 2) : '' }}"
                                                   placeholder="0,00"
                                                   class="input text-center w-28 decimal-input"
                                                   x-data="decimalInput"
                                                   x-on:blur="formatValue($event.target)"
                                                   x-on:focus="$event.target.select()">
                                        </td>
                                        <td class="td text-center">
                                            <input type="text" 
                                                   name="chi_tieus[{{ $index }}][vung_kk_dbkk]" 
                                                   value="{{ $item->vung_kk_dbkk ? \App\Helpers\NumberFormatHelper::format($item->vung_kk_dbkk, 2) : '' }}"
                                                   placeholder="0,00"
                                                   class="input text-center w-28 decimal-input"
                                                   x-data="decimalInput"
                                                   x-on:blur="formatValue($event.target)"
                                                   x-on:focus="$event.target.select()">
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="td text-center text-slate-500">Chưa có chỉ tiêu nào</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($chiTieuGiaTris->isNotEmpty())
                        <div class="mt-6 flex justify-end gap-3">
                            <a href="{{ route('admin.settings.pcgd-chi-tieu.index') }}" class="btn-soft">
                                Huỷ
                            </a>
                            <button type="submit" class="btn-primary">
                                💾 Lưu tất cả
                            </button>
                        </div>
                    @endif
                </form>
            </article>
        @elseif($selectedChuongTrinh && $chiTieuGiaTris->isEmpty())
            <article class="card">
                <div class="text-center py-8 text-slate-500">
                    <svg class="w-16 h-16 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="font-medium">Chương trình này chưa có chỉ tiêu nào.</p>
                    <p class="text-sm mt-2">Vui lòng tạo chỉ tiêu và gắn vào chương trình này.</p>
                    <a href="{{ route('admin.settings.chi-tieu.create') }}" 
                       class="btn-primary mt-4 inline-block">
                        ➕ Tạo chỉ tiêu mới
                    </a>
                </div>
            </article>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            // Component xử lý format số thập phân
            Alpine.data('decimalInput', () => ({
                formatValue(input) {
                    let value = input.value.trim();
                    if (!value) return;

                    // Parse: Chấp nhận cả format VN (70,00) và Quốc tế (70.00)
                    // Bỏ dấu chấm phân cách hàng nghìn
                    value = value.replace(/\./g, '');
                    // Chuyển dấu phẩy thành dấu chấm
                    value = value.replace(/,/g, '.');
                    
                    let number = parseFloat(value);
                    
                    if (isNaN(number)) {
                        input.value = '';
                        return;
                    }

                    // Giới hạn 0-100
                    if (number < 0) number = 0;
                    if (number > 100) number = 100;

                    // Format lại để hiển thị theo cấu hình user
                    @if(auth()->check() && auth()->user()->getDecimalFormat())
                        @if(auth()->user()->getDecimalFormat()->decimal_separator === ',')
                            // Format Việt Nam: 70,00
                            input.value = number.toFixed(2).replace('.', ',');
                        @else
                            // Format Quốc tế: 70.00
                            input.value = number.toFixed(2);
                        @endif
                    @else
                        // Mặc định: Format Việt Nam (70,00)
                        input.value = number.toFixed(2).replace('.', ',');
                    @endif
                }
            }));
        });
    </script>
@endpush
