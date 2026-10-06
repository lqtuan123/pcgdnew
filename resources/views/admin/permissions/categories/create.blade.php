@extends('layouts.admin')

@section('title', 'Tạo danh mục mới')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Tạo danh mục mới</h1>
        <a href="{{ route('admin.permissions.categories') }}" class="btn-soft">
            <i class="fas fa-arrow-left mr-2"></i> Quay lại
        </a>
    </div>

    <!-- Form -->
    <article class="card">
        <form method="POST" action="{{ route('admin.permissions.categories.store') }}">
            @csrf
            <div class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Mã danh mục *</label>
                        <input 
                            type="text" 
                            name="code" 
                            value="{{ old('code') }}"
                            class="w-full rounded-lg border px-3 py-2 @error('code') border-red-500 @enderror"
                            placeholder="Ví dụ: education"
                            required
                        >
                        @error('code')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-500 mt-1">Mã danh mục phải là duy nhất, sử dụng chữ thường</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Tên danh mục *</label>
                        <input 
                            type="text" 
                            name="name" 
                            value="{{ old('name') }}"
                            class="w-full rounded-lg border px-3 py-2 @error('name') border-red-500 @enderror"
                            placeholder="Tên hiển thị của danh mục"
                            required
                        >
                        @error('name')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Mô tả</label>
                    <textarea 
                        name="description" 
                        rows="3"
                        class="w-full rounded-lg border px-3 py-2 @error('description') border-red-500 @enderror"
                        placeholder="Mô tả chi tiết về danh mục này..."
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Màu sắc *</label>
                        <div class="flex items-center gap-2">
                            <input 
                                type="color" 
                                name="color" 
                                value="{{ old('color', '#3B82F6') }}"
                                class="w-12 h-10 rounded border @error('color') border-red-500 @enderror"
                                required
                            >
                            <input 
                                type="text" 
                                value="{{ old('color', '#3B82F6') }}"
                                class="flex-1 rounded-lg border px-3 py-2 @error('color') border-red-500 @enderror"
                                placeholder="#3B82F6"
                                readonly
                            >
                        </div>
                        @error('color')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Thứ tự</label>
                        <input 
                            type="number" 
                            name="sort_order" 
                            value="{{ old('sort_order', 0) }}"
                            min="0"
                            class="w-full rounded-lg border px-3 py-2 @error('sort_order') border-red-500 @enderror"
                            placeholder="0"
                        >
                        @error('sort_order')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-500 mt-1">Số càng nhỏ càng hiển thị trước</p>
                    </div>

                    <div class="flex items-center">
                        <label class="flex items-center">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1" 
                                class="rounded"
                                @checked(old('is_active', true))
                            >
                            <span class="ml-2 text-sm text-slate-700">Kích hoạt danh mục</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t">
                <a href="{{ route('admin.permissions.categories') }}" class="btn-soft">Hủy</a>
                <button type="submit" class="btn-primary">Tạo danh mục</button>
            </div>
        </form>
    </article>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const colorPicker = document.querySelector('input[type="color"]');
    const colorText = document.querySelector('input[type="text"]');
    
    colorPicker.addEventListener('input', function() {
        colorText.value = this.value;
    });
    
    colorText.addEventListener('input', function() {
        if (this.value.match(/^#[0-9A-Fa-f]{6}$/)) {
            colorPicker.value = this.value;
        }
    });
});
</script>
@endpush
@endsection























































