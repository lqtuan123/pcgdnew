@extends('layouts.admin')
@section('title', 'Quản lý tiêu chí thống kê')
@section('crumb', 'Quản lý / Tiêu chí thống kê')

@section('content')
<div class="space-y-6">
  <!-- Header -->
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold">Tiêu chí thống kê</h1>
        @if($reportDetail)
          <p class="text-sm text-slate-600 mt-1">
            Báo cáo: <strong>{{ $reportDetail->name }}</strong> 
            ({{ $reportDetail->reportGroup->name }})
          </p>
        @endif
      </div>
      <div class="flex items-center gap-2">
        <a href="{{ route('admin.statistical-formulas.create', ['report_detail_id' => $reportDetail->id ?? '']) }}" 
           class="btn-primary">+ Thêm tiêu chí</a>
        @if($reportDetail)
          <a href="{{ route('admin.reports.show', $reportDetail->reportGroup) }}" 
             class="btn-soft">← Quay lại</a>
        @else
          <a href="{{ route('admin.reports.index') }}" 
             class="btn-soft">← Quay lại</a>
        @endif
      </div>
    </div>
  </div>

  <!-- Danh sách tiêu chí -->
  <div class="card">
    @if($statisticalFormulas->count() > 0)
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b">
            <tr>
              <th class="text-left p-3 font-medium">Tiêu chí</th>
              <th class="text-left p-3 font-medium">Công thức</th>
              <th class="text-left p-3 font-medium">Mô tả</th>
              <th class="text-left p-3 font-medium">Báo cáo</th>
              <th class="text-left p-3 font-medium">Trạng thái</th>
              <th class="text-left p-3 font-medium">Thứ tự</th>
              <th class="text-right p-3 font-medium">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            @foreach($statisticalFormulas as $formula)
              <tr class="border-b hover:bg-slate-50">
                <td class="p-3">
                  <div class="font-medium">{{ $formula->criteria }}</div>
                </td>
                <td class="p-3">
                  <code class="text-xs bg-slate-100 px-2 py-1 rounded">{{ $formula->formula }}</code>
                </td>
                <td class="p-3">
                  @if($formula->description)
                    <div class="text-sm">{{ Str::limit($formula->description, 50) }}</div>
                  @else
                    <span class="text-xs text-slate-400">Chưa có mô tả</span>
                  @endif
                </td>
                <td class="p-3">
                  <div class="text-sm">
                    <div class="font-medium">{{ $formula->reportDetail->name }}</div>
                    <div class="text-xs text-slate-500">{{ $formula->reportDetail->reportGroup->name }}</div>
                  </div>
                </td>
                <td class="p-3">
                  @if($formula->is_active)
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
                  <span class="text-xs text-slate-500">{{ $formula->sort_order }}</span>
                </td>
                <td class="p-3">
                  <div class="flex items-center justify-end gap-1">
                    <a href="{{ route('admin.statistical-formulas.edit', $formula) }}" 
                       class="p-1 text-green-600 hover:bg-green-50 rounded" 
                       title="Chỉnh sửa">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                      </svg>
                    </a>
                    <button @click="$dispatch('confirm-delete', { 
                      action: '{{ route('admin.statistical-formulas.destroy', $formula) }}', 
                      name: '{{ $formula->criteria }}',
                      type: 'tiêu chí thống kê'
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

      <!-- Pagination -->
      <div class="mt-4">
        {{ $statisticalFormulas->appends(request()->query())->links() }}
      </div>
    @else
      <div class="text-center py-8 text-slate-500">
        <p>Chưa có tiêu chí thống kê nào</p>
        <a href="{{ route('admin.statistical-formulas.create', ['report_detail_id' => $reportDetail->id ?? '']) }}" 
           class="btn-primary mt-4">Thêm tiêu chí đầu tiên</a>
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
