@extends('layouts.admin')
@section('title', 'Sửa chỉ tiêu')
@section('crumb', 'Cài đặt / Chỉ tiêu / Sửa')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.chi-tieu.index') }}" class="text-slate-600 hover:text-slate-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h2 class="text-xl font-semibold">Sửa chỉ tiêu: {{ $chiTieu->ten_chi_tieu }}</h2>
        </div>

        <form method="POST" action="{{ route('admin.settings.chi-tieu.update', $chiTieu) }}">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Cột 1: Thông tin chỉ tiêu --}}
                <div class="lg:col-span-2">
                    <article class="card">
                        <h3 class="text-lg font-semibold mb-4">Thông tin chỉ tiêu</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Mã chỉ tiêu <span class="text-rose-600">*</span>
                                </label>
                                <input type="text" 
                                       name="ma_chi_tieu" 
                                       value="{{ old('ma_chi_tieu', $chiTieu->ma_chi_tieu) }}"
                                       class="input w-full"
                                       required>
                                @error('ma_chi_tieu')
                                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    Tên chỉ tiêu <span class="text-rose-600">*</span>
                                </label>
                                <input type="text" 
                                       name="ten_chi_tieu" 
                                       value="{{ old('ten_chi_tieu', $chiTieu->ten_chi_tieu) }}"
                                       class="input w-full"
                                       required>
                                @error('ten_chi_tieu')
                                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Mô tả</label>
                                <textarea name="mo_ta" 
                                          rows="3" 
                                          class="input w-full">{{ old('mo_ta', $chiTieu->mo_ta) }}</textarea>
                                @error('mo_ta')
                                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" 
                                           name="is_active" 
                                           value="1" 
                                           {{ old('is_active', $chiTieu->is_active) ? 'checked' : '' }}
                                           class="rounded">
                                    <span class="text-sm text-slate-700">Hoạt động</span>
                                </label>
                            </div>
                        </div>
                    </article>
                </div>

                {{-- Cột 2: Chọn chương trình --}}
                <div>
                    <article class="card sticky top-4">
                        <h3 class="text-lg font-semibold mb-4">Áp dụng cho chương trình</h3>
                        <p class="text-sm text-slate-600 mb-4">Chọn chương trình áp dụng chỉ tiêu này</p>

                        @php
                            $selectedChuongTrinhIds = old('chuong_trinh_ids', $chiTieu->chiTieuGiaTris->pluck('chuong_trinh_id')->toArray());
                        @endphp

                        <div class="space-y-2">
                            @foreach($chuongTrinhs as $ct)
                                <label class="flex items-center gap-2 p-2 hover:bg-slate-50 rounded">
                                    <input type="checkbox" 
                                           name="chuong_trinh_ids[]" 
                                           value="{{ $ct->id }}"
                                           {{ in_array($ct->id, $selectedChuongTrinhIds) ? 'checked' : '' }}
                                           class="rounded">
                                    <span class="text-sm">{{ $ct->ten_chuong_trinh }}</span>
                                </label>
                            @endforeach
                        </div>

                        @error('chuong_trinh_ids')
                            <div class="text-xs text-rose-600 mt-2">{{ $message }}</div>
                        @enderror

                        <div class="mt-4 text-xs text-slate-500 bg-blue-50 p-3 rounded">
                            💡 Có thể chọn nhiều chương trình cùng lúc
                        </div>
                    </article>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-2">
                <button type="submit" class="btn-primary">Cập nhật</button>
                <a href="{{ route('admin.settings.chi-tieu.index') }}" class="btn-soft">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
