<div id="iss-meta" data-total="{{ $issuers->total() }}" class="hidden"></div>

<div class="-mx-4 mt-3 overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
    <thead class="bg-slate-50">
      <tr>
        <th class="th text-left">#</th>
        <th class="th text-left">Mã</th>
        <th class="th text-left">Tên đơn vị cấp</th>
        <th class="th text-left">Số VB</th>
        <th class="th text-right">Thao tác</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-200">
      @forelse($issuers as $i => $r)
        <tr class="hover:bg-slate-50">
          <td class="td">{{ $issuers->firstItem() + $i }}</td>
          <td class="td font-mono">{{ $r->code ?: '—' }}</td>
          <td class="td">{{ $r->name }}</td>
          <td class="td">{{ $r->documents_count }}</td>
          <td class="td text-right">
            <a href="{{ route('admin.settings.issuers.edit',$r) }}" class="text-amber-600 hover:underline mr-3">Sửa</a>
            <button type="button" class="text-rose-600 hover:underline"
                    @click="$dispatch('confirm-delete', {action: '{{ route('admin.settings.issuers.destroy',$r) }}', name: @js($r->name)})">
              Xoá
            </button>
          </td>
        </tr>
      @empty
        <tr><td class="td" colspan="5">Không có dữ liệu.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $issuers->withQueryString()->links() }}</div>
