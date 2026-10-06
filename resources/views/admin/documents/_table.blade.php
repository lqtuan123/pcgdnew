@php use Illuminate\Support\Str; @endphp
<div id="doc-meta" data-total="{{ $documents->total() }}" class="hidden"></div>

<div class="-mx-4 mt-3 overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
    <thead class="bg-slate-50">
      <tr>
        <th class="th text-left">#</th>
        <th class="th text-left">Số/Ký hiệu</th>
        <th class="th text-left">Tiêu đề</th>
        <th class="th text-left">Ngày ban hành</th>
        <th class="th text-left">Đơn vị cấp</th>
        <th class="th text-left">Trạng thái</th>
        <th class="th text-right">Thao tác</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-200">
      @forelse($documents as $i => $d)
        <tr class="hover:bg-slate-50">
          <td class="td">{{ $documents->firstItem() + $i }}</td>
          <td class="td font-mono">{{ $d->code ?: '—' }}</td>
          <td class="td">{{ Str::limit($d->title, 80) }}</td>
          <td class="td">{{ optional($d->issued_date)->format('d/m/Y') ?: '—' }}</td>
          <td class="td">{{ optional($d->issuer)->name ?: '—' }}</td>
          <td class="td">
            @switch($d->status)
              @case('draft')     <span class="px-2 py-0.5 text-xs rounded-full bg-slate-100 text-slate-700">Nháp</span> @break
              @case('archived')  <span class="px-2 py-0.5 text-xs rounded-full bg-amber-50 text-amber-700">Lưu trữ</span> @break
              @default           <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-50 text-emerald-700">Công bố</span>
            @endswitch
          </td>
          <td class="td text-right">
            <div class="flex flex-wrap justify-begin gap-2">
              @if($d->file_path)
                <a href="{{ route('admin.documents.download',$d) }}" class="text-blue-600 hover:underline mr-3">Tải</a>
              @endif
              <a href="{{ route('admin.documents.show',$d) }}" class="text-blue-600 hover:underline mr-3">Xem</a>
              <a href="{{ route('admin.documents.edit',$d) }}" class="text-amber-600 hover:underline mr-3">Sửa</a>
              <button type="button" class="text-rose-600 hover:underline"
                      @click="$dispatch('confirm-delete', {action: '{{ route('admin.documents.destroy',$d) }}', name: @js($d->title)})">
                Xoá
              </button>
            </div>
          </td>
        </tr>
      @empty
        <tr><td class="td" colspan="7">Không có dữ liệu.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="mt-3">{{ $documents->withQueryString()->links() }}</div>
