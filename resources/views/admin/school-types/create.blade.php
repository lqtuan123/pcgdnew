@extends('layouts.admin')

@section('title', 'Thêm loại hình trường học')
@section('crumb', 'Loại hình trường học')

@section('content')
<div class="space-y-6">
  <article class="card">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Thêm loại hình trường học mới</h2>
      <a href="{{ route('admin.school-types.index') }}" class="btn-soft">
        <i class="fas fa-arrow-left mr-2"></i>Quay lại
      </a>
    </div>

    <form action="{{ route('admin.school-types.store') }}" method="POST" class="mt-6">
      @csrf
      
      <div class="space-y-4">
        <div>
          <label for="name" class="block text-sm font-medium text-slate-700 mb-2">
            Tên loại hình trường học <span class="text-red-500">*</span>
          </label>
          <input type="text" 
                 id="name" 
                 name="name" 
                 value="{{ old('name') }}" 
                 placeholder="Ví dụ: Lớp mầm non, Lớp mẫu giáo, Trường tiểu học..."
                 class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('name') border-red-500 @enderror"
                 required>
          @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
          @enderror
        </div>
      </div>

      <div class="flex items-center gap-3 mt-6 pt-4 border-t border-slate-200">
        <button type="submit" class="btn-primary">
          <i class="fas fa-save mr-2"></i>Lưu
        </button>
        <a href="{{ route('admin.school-types.index') }}" class="btn-soft">
          <i class="fas fa-times mr-2"></i>Hủy
        </a>
      </div>
    </form>
  </article>
</div>
@endsection
