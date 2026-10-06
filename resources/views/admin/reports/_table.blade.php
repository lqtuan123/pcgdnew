<div id="report-meta" data-total="{{ $reportGroups->count() }} nhóm báo cáo"></div>

<div class="overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 border-b">
      <tr>
        <th class="text-left p-3 font-medium">Nhóm báo cáo</th>
        <th class="text-left p-3 font-medium">Mã</th>
        <th class="text-left p-3 font-medium">Số báo cáo</th>
        <th class="text-left p-3 font-medium">Trạng thái</th>
        <th class="text-left p-3 font-medium">Thứ tự</th>
        <th class="text-right p-3 font-medium">Thao tác</th>
      </tr>
    </thead>
    <tbody>
      @forelse($reportGroups as $reportGroup)
        <tr class="border-b hover:bg-slate-50">
          <td class="p-3">
            <div class="font-medium">{{ $reportGroup->name }}</div>
            @if($reportGroup->description)
              <div class="text-xs text-slate-500 mt-1">{{ Str::limit($reportGroup->description, 50) }}</div>
            @endif
          </td>
          <td class="p-3">
            <code class="text-xs bg-slate-100 px-2 py-1 rounded">{{ $reportGroup->code }}</code>
          </td>
          <td class="p-3">
            <a href="{{ route('admin.reports.show', $reportGroup) }}" 
               class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors cursor-pointer">
              {{ $reportGroup->reportDetails->count() }} báo cáo
            </a>
          </td>
          <td class="p-3">
            @if($reportGroup->is_active)
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
            <span class="text-xs text-slate-500">{{ $reportGroup->sort_order }}</span>
          </td>
          <td class="p-3">
            <div class="flex items-center justify-end gap-1">
              <a href="{{ route('admin.reports.edit', $reportGroup) }}" 
                 class="p-1 text-green-600 hover:bg-green-50 rounded" 
                 title="Chỉnh sửa">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
              </a>
              <button @click="$dispatch('confirm-delete', { 
                action: '{{ route('admin.reports.destroy', $reportGroup) }}', 
                name: '{{ $reportGroup->name }}',
                type: 'nhóm báo cáo'
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
      @empty
        <tr>
          <td colspan="6" class="p-8 text-center text-slate-500">
            Không có nhóm báo cáo nào
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

@if($reportGroups->hasPages())
  <div class="mt-4">
    {{ $reportGroups->links() }}
  </div>
@endif
