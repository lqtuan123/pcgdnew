<div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between rounded-xl border bg-slate-50 p-3">
  <div class="text-sm">
    <div class="font-medium">
      Tổng số đối tượng: <span class="tabular-nums">{{ number_format($rows->total()) }}</span>
    </div>
    <div class="text-slate-600">
      Hiển thị: <span class="tabular-nums">{{ $rows->firstItem() ?? 0 }}</span>–<span class="tabular-nums">{{ $rows->lastItem() ?? 0 }}</span>
      / trang {{ $rows->currentPage() }}
      <template x-if="selectedCount>0">
        <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-amber-800">Đã chọn: <b x-text="selectedCount"></b></span>
      </template>
    </div>
  </div>

  <div class="flex flex-wrap items-center gap-2">
    @if(auth()->user()->hasPermission('quan_ly_doi_tuong_dieu_tra', 'delete'))
      <form x-ref="bulkSelForm" method="POST" action="{{ $softUrl }}">
        @csrf
        <button type="button"
          class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="selectedCount===0"
          x-text="selectedCount>0 ? `Xóa tạm (${selectedCount})` : 'Xóa tạm'"
          @click="confirmBulkDelete()"></button>
      </form>

      {{-- <form x-ref="bulkHardDeleteForm" method="POST" action="{{ $hardUrl }}">
        @csrf
        <button type="button"
          class="rounded-lg bg-red-800 px-3 py-2 text-white hover:bg-red-900 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="selectedCount===0"
          x-text="selectedCount>0 ? `Xóa (${selectedCount})` : 'Xóa'"
          @click="confirmBulkHardDelete()"></button>
      </form> --}}
    @endif
  </div>
</div>
