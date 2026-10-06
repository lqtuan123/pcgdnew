{{-- resources/views/admin/schools/_table.blade.php --}}
<div class="-mx-4 mt-3 overflow-x-auto">
  <table class="min-w-full divide-y divide-slate-200">
    <thead class="bg-slate-50">
      <tr>
        <th class="th text-left">#</th>
        <th class="th text-left">Mã</th>
        <th class="th text-left">Tên trường</th>
        <th class="th text-left">Loại hình</th>
        <th class="th text-left">Bậc học</th>
        <th class="th text-left">Phường/Xã (cũ)</th>
        <th class="th text-left">Cấp 3 (mới)</th>
        <th class="th text-right">Thao tác</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-200">
      @forelse($schools as $i => $s)
        <tr class="hover:bg-slate-50">
          <td class="td">{{ $schools->firstItem() + $i }}</td>
          <td class="td font-mono">{{ $s->code }}</td>
          <td class="td">{{ $s->name }}</td>
          <td class="td">{{ optional($s->schoolType)->name }}</td>
          <td class="td">{{ optional($s->educationalLevel)->name }}</td>
          <td class="td">
            <div class="truncate">{{ optional($s->wardUnit)->name ?? '—' }}</div>
            <div class="text-xs text-slate-400">{{ $s->ward_code ?: '—' }}</div>
          </td>
          <td class="td">
            <div class="truncate">{{ optional($s->newWardUnit)->name ?? '—' }}</div>
            <div class="text-xs text-slate-400">{{ $s->ward_code_new ?: '—' }}</div>
          </td>
          <td class="td text-right">
            <a href="{{ route('admin.schools.show',$s) }}" class="text-blue-600 hover:underline mr-3">Xem</a>
            <a href="{{ route('admin.school-areas.index',$s) }}" class="text-green-600 hover:underline mr-3" title="Khu vực tuyển sinh">
              <i class="fas fa-map-marker-alt"></i>
            </a>
            <a href="{{ route('admin.schools.edit',$s) }}" class="text-amber-600 hover:underline mr-3">Sửa</a>
            <button type="button" class="text-rose-600 hover:underline"
              @click="$dispatch('confirm-delete', { action: '{{ route('admin.schools.destroy',$s) }}', name: @js($s->name) })">
              Xoá
            </button>
          </td>
        </tr>
      @empty
        <tr><td class="td" colspan="8">Không có dữ liệu.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
