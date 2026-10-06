@extends('layouts.admin')
@section('title','Sửa danh mục hướng dẫn')
@section('crumb','Cài đặt / Hướng dẫn / Sửa')

@section('content')
<article class="card">
    <form method="POST" action="{{ route('admin.settings.guides.update', $category->id) }}">
        @csrf
        @method('PUT')
        <div class="grid gap-3 md:grid-cols-12">
            <div class="md:col-span-6">
                <label class="block text-xs text-slate-500 mb-1">Tên danh mục <span class="text-rose-600">*</span></label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" 
                       class="w-full rounded-lg border px-3 py-2" 
                       placeholder="Nhập tên danh mục" required>
                @error('name')
                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="md:col-span-6">
                <label class="block text-xs text-slate-500 mb-1">Loại người dùng <span class="text-rose-600">*</span></label>
                <select name="role_type" class="w-full rounded-lg border px-3 py-2" required>
                    <option value="">Chọn loại người dùng</option>
                    <option value="admin" {{ old('role_type', $category->role_type) == 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="user" {{ old('role_type', $category->role_type) == 'user' ? 'selected' : '' }}>User</option>
                </select>
                @error('role_type')
                    <div class="text-xs text-rose-600 mt-1">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mt-4 flex items-center gap-2">
            <button class="btn-primary">Cập nhật</button>
            <a href="{{ route('admin.settings.guides.index') }}" class="btn-soft">Hủy</a>
        </div>
    </form>
</article>
@endsection
