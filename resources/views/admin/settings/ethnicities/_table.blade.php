<div class="-mx-4 mt-3 overflow-x-auto">
<table class="min-w-full divide-y divide-slate-200">
<thead class="bg-slate-50">
<tr>
<th class="th text-left">#</th>
<th class="th text-left">Mã</th>
<th class="th text-left">Tên dân tộc</th>
<th class="th text-left">Nhóm</th>
<th class="th text-left">Ghi chú</th>
<th class="th text-right">Thao tác</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-200">
@forelse($ethnicities as $i => $e)
<tr class="hover:bg-slate-50">
<td class="td">{{ $ethnicities->firstItem() + $i }}</td>
<td class="td font-mono">{{ $e->code ?: '—' }}</td>
<td class="td">{{ $e->name }}</td>
<td class="td">
@if($e->is_minority)
<span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Thiểu số</span>
@else
<span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Không thiểu số</span>
@endif
</td>
<td class="td">{{ Str::limit($e->note, 70) }}</td>
<td class="td text-right">
<a href="{{ route('admin.settings.ethnicities.edit',$e) }}" class="text-amber-600 hover:underline mr-3">Sửa</a>
<button type="button" class="text-rose-600 hover:underline"
@click="$dispatch('confirm-delete', {action: '{{ route('admin.settings.ethnicities.destroy',$e) }}', name: @js($e->name)})">
Xoá
</button>
</td>
</tr>
@empty
<tr><td class="td" colspan="6">Không có dữ liệu.</td></tr>
@endforelse
</tbody>
</table>
</div>
<div class="mt-3">{{ $ethnicities->withQueryString()->links() }}</div>