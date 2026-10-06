@extends('layouts.admin')
@section('title','Chi tiết nhóm báo cáo')
@section('crumb','Quản lý / Báo cáo / Chi tiết')

@section('content')
<div class="space-y-6">
  <!-- Thông tin nhóm báo cáo -->
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold">{{ $reportGroup->name }}</h1>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.reports.edit', $reportGroup) }}" class="btn-primary">Chỉnh sửa</a>
        <a href="{{ route('admin.reports.index') }}" class="btn-soft">← Quay lại</a>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Mã nhóm báo cáo</label>
        <code class="text-sm bg-slate-100 px-3 py-2 rounded-lg block">{{ $reportGroup->code }}</code>
      </div>
      
      <div>
        <label class="block text-xs text-slate-500 mb-1">Trạng thái</label>
        @if($reportGroup->is_active)
          <span class="inline-flex items-center px-3 py-2 rounded-full text-sm bg-green-100 text-green-800">
            Hoạt động
          </span>
        @else
          <span class="inline-flex items-center px-3 py-2 rounded-full text-sm bg-gray-100 text-gray-800">
            Tạm dừng
          </span>
        @endif
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1">Thứ tự sắp xếp</label>
        <div class="text-sm">{{ $reportGroup->sort_order }}</div>
      </div>

      <div>
        <label class="block text-xs text-slate-500 mb-1">Số báo cáo chi tiết</label>
        <a href="{{ route('admin.reports.show', $reportGroup) }}" 
           class="inline-flex items-center px-3 py-2 rounded-full text-sm bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors cursor-pointer">
          {{ $reportGroup->reportDetails->count() }} báo cáo
        </a>
      </div>

      @if($reportGroup->description)
        <div class="md:col-span-2">
          <label class="block text-xs text-slate-500 mb-1">Mô tả</label>
          <div class="text-sm text-slate-700">{{ $reportGroup->description }}</div>
        </div>
      @endif
    </div>
  </div>

  <!-- Danh sách báo cáo chi tiết -->
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-lg font-semibold">Báo cáo chi tiết</h2>
      <a href="{{ route('admin.report-details.create', ['group_id' => $reportGroup->id]) }}" class="btn-primary">+ Thêm báo cáo</a>
    </div>

    @if($reportGroup->reportDetails->count() > 0)
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b">
            <tr>
              <th class="text-left p-3 font-medium">Tên báo cáo</th>
              <th class="text-left p-3 font-medium">Mã</th>
              <th class="text-left p-3 font-medium">Giá trị</th>
              <th class="text-left p-3 font-medium">Tiêu chí</th>
              <th class="text-left p-3 font-medium">Trạng thái</th>
              <th class="text-left p-3 font-medium">Thứ tự</th>
              <th class="text-right p-3 font-medium">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            @foreach($reportGroup->reportDetails->sortBy('sort_order') as $detail)
              <tr class="border-b hover:bg-slate-50">
                <td class="p-3">
                  <div class="font-medium">{{ $detail->name }}</div>
                  @if($detail->description)
                    <div class="text-xs text-slate-500 mt-1">{{ Str::limit($detail->description, 50) }}</div>
                  @endif
                </td>
                <td class="p-3">
                  <code class="text-xs bg-slate-100 px-2 py-1 rounded">{{ $detail->code }}</code>
                </td>
                <td class="p-3">
                  @if($detail->value)
                    <div class="text-sm">{{ Str::limit($detail->value, 30) }}</div>
                  @else
                    <span class="text-xs text-slate-400">Chưa có dữ liệu</span>
                  @endif
                </td>
                <td class="p-3">
                  <a href="{{ route('admin.statistical-formulas.index', ['report_detail_id' => $detail->id]) }}" 
                     class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors cursor-pointer">
                    {{ $detail->statisticalFormulas->count() }} tiêu chí
                  </a>
                </td>
                <td class="p-3">
                  @if($detail->is_active)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-green-100 text-green-800">
                      Hoạt động
                    </span>
                  @else
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-800">
                      Tạm dừng
                    </span>
                  @endif
                </td>
                <td class="p-3">
                  <span class="text-xs text-slate-500">{{ $detail->sort_order }}</span>
                </td>
                <td class="p-3">
                  <div class="flex items-center justify-end gap-1">
                    <a href="{{ route('admin.report-details.edit', $detail) }}" 
                       class="p-1 text-green-600 hover:bg-green-50 rounded" 
                       title="Chỉnh sửa">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                      </svg>
                    </a>
                    <button @click="$dispatch('confirm-delete', { 
                      action: '{{ route('admin.report-details.destroy', $detail) }}', 
                      name: '{{ $detail->name }}',
                      type: 'báo cáo chi tiết'
                    })"
                    class="p-1 text-rose-600 hover:bg-rose-50 rounded" 
                    title="Xoá">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="text-center py-8 text-slate-500">
        <p>Chưa có báo cáo chi tiết nào</p>
        <a href="{{ route('admin.report-details.create', ['group_id' => $reportGroup->id]) }}" class="btn-primary mt-4">Thêm báo cáo đầu tiên</a>
      </div>
    @endif
  </div>
</div>

{{-- modal confirm xoá --}}
<div x-data="{ open:false, action:'', name:'', type:'' }"
     @confirm-delete.window="open=true; action=$event.detail.action; name=$event.detail.name; type=$event.detail.type">
  <div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
    <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
      <h3 class="font-semibold text-lg">Xoá <span x-text="type"></span>?</h3>
      <p class="mt-2 text-sm text-slate-600">Bạn chắc chắn muốn xoá <span class="font-medium" x-text="name"></span>?</p>
      <form :action="action" method="POST" class="mt-4 flex justify-end gap-2">
        @csrf @method('DELETE')
        <button type="button" class="btn-soft" @click="open=false">Huỷ</button>
        <button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 text-white">Xoá</button>
      </form>
    </div>
  </div>
</div>
@endsection
