<div class="-mx-4 mt-3 overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
    <thead class="bg-slate-50">
      <tr>
        <th class="th text-left">
          <input 
            type="checkbox" 
            id="select-all-checkbox"
            class="rounded border-slate-300 text-rose-600 focus:ring-rose-500"
          >
        </th>
        <th class="th text-left">#</th>
        <th class="th text-left">Mã</th>
        <th class="th text-left">Tên tôn giáo</th>
        <th class="th text-left">Trạng thái</th>
        <th class="th text-left">Ghi chú</th>
        <th class="th text-right">Thao tác</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-200">
      @forelse($religions as $i => $r)
        <tr class="hover:bg-slate-50">
          <td class="td">
            <input 
              type="checkbox" 
              class="item-checkbox rounded border-slate-300 text-rose-600 focus:ring-rose-500"
              value="{{ $r->id }}"
              data-id="{{ $r->id }}"
            >
          </td>
          <td class="td">{{ $religions->firstItem() + $i }}</td>
          <td class="td font-mono">{{ $r->code ?: '—' }}</td>
          <td class="td">{{ $r->name }}</td>
          <td class="td">
            @if($r->is_official)
              <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Chính thức</span>
            @else
              <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Khác</span>
            @endif
          </td>
          <td class="td">{{ Str::limit($r->note, 70) }}</td>
          <td class="td text-right">
            <a href="{{ route('admin.settings.religions.edit',$r) }}" class="text-amber-600 hover:underline mr-3">Sửa</a>
            <button type="button" class="text-rose-600 hover:underline"
                    @click="$dispatch('confirm-delete', {action: '{{ route('admin.settings.religions.destroy',$r) }}', name: @js($r->name)})">
              Xoá
            </button>
          </td>
        </tr>
      @empty
        <tr><td class="td text-center" colspan="7">Không có dữ liệu.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
<div class="mt-3">{{ $religions->withQueryString()->links() }}</div>
