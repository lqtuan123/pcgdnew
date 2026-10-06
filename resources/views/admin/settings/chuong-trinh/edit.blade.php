@extends('layouts.admin')
@section('title', 'Sửa chương trình')
@section('crumb', 'Cài đặt / Chương trình / Sửa')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.settings.chuong-trinh.index') }}" class="text-slate-600 hover:text-slate-800">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <h2 class="text-xl font-semibold">Sửa chương trình: {{ $chuongTrinh->ten_chuong_trinh }}</h2>
        </div>

        <article class="card max-w-2xl">
            <form method="POST" action="{{ route('admin.settings.chuong-trinh.update', $chuongTrinh) }}">
                @csrf
                @method('PUT')
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Mã chương trình <span class="text-rose-600">*</span>
                        </label>
                        <input type="text" 
                               name="ma_chuong_trinh" 
                               value="{{ old('ma_chuong_trinh', $chuongTrinh->ma_chuong_trinh) }}"
                               class="input w-full"
                               required>
                        @error('ma_chuong_trinh')
                            <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Tên chương trình <span class="text-rose-600">*</span>
                        </label>
                        <input type="text" 
                               name="ten_chuong_trinh" 
                               value="{{ old('ten_chuong_trinh', $chuongTrinh->ten_chuong_trinh) }}"
                               class="input w-full"
                               required>
                        @error('ten_chuong_trinh')
                            <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Mô tả</label>
                        <textarea name="mo_ta" 
                                  rows="3" 
                                  class="input w-full">{{ old('mo_ta', $chuongTrinh->mo_ta) }}</textarea>
                        @error('mo_ta')
                            <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" 
                                   name="is_active" 
                                   value="1" 
                                   {{ old('is_active', $chuongTrinh->is_active) ? 'checked' : '' }}
                                   class="rounded">
                            <span class="text-sm text-slate-700">Hoạt động</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-2">
                    <button type="submit" class="btn-primary">Cập nhật</button>
                    <a href="{{ route('admin.settings.chuong-trinh.index') }}" class="btn-soft">Huỷ</a>
                </div>
            </form>
        </article>
    </div>
@endsection

