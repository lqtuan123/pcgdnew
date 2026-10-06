@extends('layouts.admin')
@section('title', 'Thêm tiêu chí thống kê')
@section('crumb', 'Quản lý / Tiêu chí thống kê / Thêm mới')

@section('content')
<div class="space-y-6">
  <!-- Header -->
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold">Thêm tiêu chí thống kê</h1>
      <a href="{{ route('admin.statistical-formulas.index', ['report_detail_id' => $reportDetail->id ?? '']) }}" 
         class="btn-soft">← Quay lại</a>
    </div>
  </div>

  <!-- Form -->
  <div class="card">
    <form action="{{ route('admin.statistical-formulas.store') }}" method="POST">
      @csrf
      
      <!-- Báo cáo -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-slate-700 mb-2">Báo cáo</label>
        @if($reportDetail)
          <div class="p-4 bg-slate-50 rounded-lg border">
            <div class="font-medium text-slate-900">{{ $reportDetail->name }}</div>
            <div class="text-sm text-slate-600 mt-1">{{ $reportDetail->reportGroup->name }}</div>
          </div>
          <input type="hidden" name="report_detail_id" value="{{ $reportDetail->id }}">
        @else
          <select name="report_detail_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" required></select>
            <option value="">Chọn báo cáo</option>
            @foreach($reportDetails as $detail)
              <option value="{{ $detail->id }}">
                {{ $detail->name }} ({{ $detail->reportGroup->name }})
              </option>
            @endforeach
          </select>
          @error('report_detail_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        @endif
      </div>

      <!-- Tiêu chí -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-slate-700 mb-2">Tiêu chí *</label>
        <input type="text" name="criteria" value="{{ old('criteria') }}" 
               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
               placeholder="Nhập tiêu chí thống kê" required>
        @error('criteria')
          <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
      </div>

      <!-- Công thức -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-slate-700 mb-2">Công thức *</label>
        <input type="text" name="formula" value="{{ old('formula') }}" 
               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
               placeholder="Nhập công thức thống kê" required>
        @error('formula')
          <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
      </div>

      <!-- Mô tả -->
      <div class="mb-6">
        <label class="block text-sm font-medium text-slate-700 mb-2">Mô tả</label>
        <textarea name="description" rows="4" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-vertical" 
                  placeholder="Nhập mô tả chi tiết cho tiêu chí thống kê">{{ old('description') }}</textarea>
        @error('description')
          <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
        @enderror
      </div>

      <!-- Thứ tự và Trạng thái -->
      <div class="grid gap-4 md:grid-cols-2 mb-6">
        <!-- Thứ tự -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Thứ tự</label>
          <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" 
                 class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                 min="0">
          @error('sort_order')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>

        <!-- Trạng thái -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Trạng thái</label>
          <div class="flex items-center p-3 bg-slate-50 rounded-lg border">
            <input type="checkbox" name="is_active" value="1" 
                   {{ old('is_active', true) ? 'checked' : '' }} 
                   class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
            <span class="ml-2 text-sm text-slate-700">Hoạt động</span>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end gap-2 mt-6">
        <a href="{{ route('admin.statistical-formulas.index', ['report_detail_id' => $reportDetail->id ?? '']) }}" 
           class="btn-soft">Hủy</a>
        <button type="submit" class="btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>
@endsection
