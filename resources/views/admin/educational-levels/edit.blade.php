@extends('layouts.admin')

@section('title', 'Chỉnh sửa bậc học')
@section('crumb', 'Bậc học')

@section('content')
<div class="space-y-6">
  <article class="card">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Chỉnh sửa bậc học</h2>
      <a href="{{ route('admin.educational-levels.index') }}" class="btn-soft">
        <i class="fas fa-arrow-left mr-2"></i>Quay lại
      </a>
    </div>

    <form action="{{ route('admin.educational-levels.update', $educationalLevel) }}" method="POST" class="mt-6">
      @csrf
      @method('PUT')
      
      <div class="space-y-4">
        <div>
          <label for="name" class="block text-sm font-medium text-slate-700 mb-2">
            Tên bậc học <span class="text-red-500">*</span>
          </label>
          <input type="text" 
                 id="name" 
                 name="name" 
                 value="{{ old('name', $educationalLevel->name) }}" 
                 placeholder="Ví dụ: Tiểu học, Trung học cơ sở, Trung học phổ thông..."
                 class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('name') border-red-500 @enderror"
                 required>
          @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
          @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label for="age_min" class="block text-sm font-medium text-slate-700 mb-2">
              Độ tuổi tối thiểu
            </label>
            <input type="number" 
                   id="age_min" 
                   name="age_min" 
                   value="{{ old('age_min', $educationalLevel->age_min) }}" 
                   min="0" 
                   max="25"
                   placeholder="Ví dụ: 6"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('age_min') border-red-500 @enderror">
            @error('age_min')
              <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-slate-500">Để trống nếu không cần giới hạn</p>
          </div>

          <div>
            <label for="age_max" class="block text-sm font-medium text-slate-700 mb-2">
              Độ tuổi tối đa
            </label>
            <input type="number" 
                   id="age_max" 
                   name="age_max" 
                   value="{{ old('age_max', $educationalLevel->age_max) }}" 
                   min="0" 
                   max="25"
                   placeholder="Ví dụ: 11"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 @error('age_max') border-red-500 @enderror">
            @error('age_max')
              <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-slate-500">Để trống nếu không cần giới hạn</p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3 mt-6 pt-4 border-t border-slate-200">
        <button type="submit" class="btn-primary">
          <i class="fas fa-save mr-2"></i>Cập nhật
        </button>
        <a href="{{ route('admin.educational-levels.index') }}" class="btn-soft">
          <i class="fas fa-times mr-2"></i>Hủy
        </a>
      </div>
    </form>
  </article>
</div>
@endsection
