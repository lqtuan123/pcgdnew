@extends('layouts.admin')

@section('title', 'Chỉnh sửa chức năng')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-slate-800">Chỉnh sửa chức năng</h1>
        <a href="{{ route('admin.permissions.functions') }}" class="btn-soft">
            <i class="fas fa-arrow-left mr-2"></i> Quay lại
        </a>
    </div>

    <!-- Form -->
    <article class="card">
        <form method="POST" action="{{ route('admin.permissions.functions.update', $function) }}">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Mã chức năng *</label>
                        <input 
                            type="text" 
                            name="code" 
                            value="{{ old('code', $function->code) }}"
                            class="w-full rounded-lg border px-3 py-2 @error('code') border-red-500 @enderror"
                            placeholder="Ví dụ: quan_ly_nguoi_dung"
                            required
                        >
                        @error('code')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        <p class="text-xs text-slate-500 mt-1">Mã chức năng phải là duy nhất, sử dụng dấu gạch dưới</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Tên chức năng *</label>
                        <input 
                            type="text" 
                            name="name" 
                            value="{{ old('name', $function->name) }}"
                            class="w-full rounded-lg border px-3 py-2 @error('name') border-red-500 @enderror"
                            placeholder="Tên hiển thị của chức năng"
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
                        rows="4"
                        class="w-full rounded-lg border px-3 py-2 @error('description') border-red-500 @enderror"
                        placeholder="Mô tả chi tiết về chức năng này..."
                    >{{ old('description', $function->description) }}</textarea>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Danh mục *</label>
                        <select 
                            name="category" 
                            class="w-full rounded-lg border px-3 py-2 @error('category') border-red-500 @enderror"
                            required
                        >
                            <option value="">Chọn danh mục</option>
                            @foreach(\App\Models\FunctionCategory::active()->ordered()->get() as $category)
                                <option value="{{ $category->code }}" @selected(old('category', $function->category) == $category->code)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <label class="flex items-center">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1" 
                                class="rounded"
                                @checked(old('is_active', $function->is_active))
                            >
                            <span class="ml-2 text-sm text-slate-700">Kích hoạt chức năng</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center justify-end gap-3 mt-8 pt-6 border-t">
                <a href="{{ route('admin.permissions.functions') }}" class="btn-soft">Hủy</a>
                <button type="submit" class="btn-primary">Cập nhật chức năng</button>
            </div>
        </form>
    </article>
</div>
@endsection
