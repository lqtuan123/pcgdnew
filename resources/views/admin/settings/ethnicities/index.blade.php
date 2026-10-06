@extends('layouts.admin')
@section('title','Dân tộc')
@section('crumb','Cài đặt / Dân tộc')


@section('content')
@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="space-y-6" x-data="ethIdx">
<div class="flex items-center gap-3">
<a href="{{ route('admin.settings.ethnicities.create') }}" class="btn-primary">+ Thêm dân tộc</a>

<!-- Bulk Delete Button -->
<button 
  type="button" 
  id="bulk-delete-btn"
  class="px-4 py-2 rounded-lg text-white font-medium transition-colors bg-gray-400 cursor-not-allowed"
  disabled
>
  <i class="fas fa-trash mr-2"></i>
  Xóa đã chọn (<span id="selected-count">0</span>)
</button>

<div class="ml-auto"></div>
</div>


<article class="card">
<form method="GET" action="{{ route('admin.settings.ethnicities.index') }}" class="grid gap-3 md:grid-cols-12">
<div class="md:col-span-4">
<label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
<input type="text" name="q" value="{{ request('q') }}" placeholder="Tên / mã dân tộc…"
class="w-full rounded-lg border px-3 py-2">
</div>
<div class="md:col-span-3">
<label class="block text-xs text-slate-500 mb-1">Nhóm</label>
<select name="is_minority" class="w-full rounded-lg border px-3 py-2">
<option value="">— Tất cả —</option>
<option value="1" @selected(request('is_minority')==='1')>Thiểu số</option>
<option value="0" @selected(request('is_minority')==='0')>Không thiểu số</option>
</select>
</div>
<div class="md:col-span-3 flex items-end gap-2">
<button class="btn-primary">Lọc</button>
<a href="{{ route('admin.settings.ethnicities.index') }}" class="btn-soft">Xoá lọc</a>
</div>
</form>
</article>
<article class="card">
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
<th class="th text-left">Tên dân tộc</th>
<th class="th text-left">Nhóm</th>
<th class="th text-left">Ghi chú</th>
<th class="th text-right">Thao tác</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-200">
@forelse($ethnicities as $i => $e)
<tr class="hover:bg-slate-50">
<td class="td">
  <input 
    type="checkbox" 
    class="item-checkbox rounded border-slate-300 text-rose-600 focus:ring-rose-500"
    value="{{ $e->id }}"
    data-id="{{ $e->id }}"
  >
</td>
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
<tr><td class="td text-center" colspan="7">Không có dữ liệu.</td></tr>
@endforelse
</tbody>
</table>
</div>


<div class="mt-3">{{ $ethnicities->withQueryString()->links() }}</div>
</article>
</div>


{{-- dùng lại modal confirm xoá như trang Trường học --}}
<div x-data="{ open:false, action:'', name:'' }"
@confirm-delete.window="open=true; action=$event.detail.action; name=$event.detail.name">
<div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
<div class="absolute inset-0 bg-black/40" @click="open=false"></div>
<div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
<h3 class="font-semibold text-lg">Xoá dân tộc?</h3>
<p class="mt-2 text-sm text-slate-600">Bạn chắc chắn muốn xoá <span class="font-medium" x-text="name"></span>?</p>
<form :action="action" method="POST" class="mt-4 flex justify-end gap-2">
@csrf @method('DELETE')
<button type="button" class="btn-soft" @click="open=false">Huỷ</button>
<button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 text-white">Xoá</button>
</form>
</div>
</div>
</div>

<!-- Modal xác nhận bulk delete -->
<div id="bulk-delete-modal" class="fixed inset-0 z-[70] grid place-items-center hidden">
  <div class="absolute inset-0 bg-black/40" onclick="closeBulkDeleteModal()"></div>

  <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
    <h3 class="font-semibold text-lg">Xoá hàng loạt?</h3>
    <p class="mt-2 text-sm text-slate-600">
      Bạn chắc chắn muốn xoá <span class="font-medium" id="modal-selected-count">0</span> dân tộc đã chọn?
      Hành động không thể hoàn tác.
    </p>

    <form action="{{ route('admin.settings.ethnicities.bulk-destroy') }}" method="POST" class="mt-4 flex justify-end gap-2">
      @csrf
      <div id="hidden-inputs"></div>
      <button type="button" class="btn-soft" onclick="closeBulkDeleteModal()">Huỷ</button>
      <button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 text-white">Xoá</button>
    </form>
  </div>
</div>
@endsection


@push('scripts')
<script>document.addEventListener('alpine:init',()=>{ Alpine.data('ethIdx',()=>({})) })</script>

<script>
// Bulk Delete JavaScript
document.addEventListener('DOMContentLoaded', function() {
  let selectedIds = [];
  
  // Elements
  const selectAllCheckbox = document.getElementById('select-all-checkbox');
  const itemCheckboxes = document.querySelectorAll('.item-checkbox');
  const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
  const selectedCountSpan = document.getElementById('selected-count');
  const modal = document.getElementById('bulk-delete-modal');
  const modalSelectedCount = document.getElementById('modal-selected-count');
  const hiddenInputsDiv = document.getElementById('hidden-inputs');
  
  // Update UI
  function updateUI() {
    const count = selectedIds.length;
    
    // Update button
    if (count === 0) {
      bulkDeleteBtn.className = 'px-4 py-2 rounded-lg text-white font-medium transition-colors bg-gray-400 cursor-not-allowed';
      bulkDeleteBtn.disabled = true;
    } else {
      bulkDeleteBtn.className = 'px-4 py-2 rounded-lg text-white font-medium transition-colors bg-rose-600 hover:bg-rose-700';
      bulkDeleteBtn.disabled = false;
    }
    
    // Update counts
    selectedCountSpan.textContent = count;
    
    // Update select all checkbox
    if (count === 0) {
      selectAllCheckbox.indeterminate = false;
      selectAllCheckbox.checked = false;
    } else if (count === itemCheckboxes.length) {
      selectAllCheckbox.indeterminate = false;
      selectAllCheckbox.checked = true;
    } else {
      selectAllCheckbox.indeterminate = true;
    }
  }
  
  // Toggle individual checkbox
  function toggleSelection(id, checked) {
    if (checked) {
      if (!selectedIds.includes(id)) {
        selectedIds.push(id);
      }
    } else {
      selectedIds = selectedIds.filter(selectedId => selectedId !== id);
    }
    updateUI();
  }
  
  // Toggle all checkboxes
  function toggleAll(checked) {
    if (checked) {
      selectedIds = Array.from(itemCheckboxes).map(cb => parseInt(cb.value));
      itemCheckboxes.forEach(cb => cb.checked = true);
    } else {
      selectedIds = [];
      itemCheckboxes.forEach(cb => cb.checked = false);
    }
    updateUI();
  }
  
  // Show bulk delete modal
  function showBulkDeleteModal() {
    if (selectedIds.length > 0) {
      modalSelectedCount.textContent = selectedIds.length;
      
      // Create hidden inputs
      hiddenInputsDiv.innerHTML = '';
      selectedIds.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = id;
        hiddenInputsDiv.appendChild(input);
      });
      
      modal.classList.remove('hidden');
    }
  }
  
  // Close bulk delete modal
  window.closeBulkDeleteModal = function() {
    modal.classList.add('hidden');
  };
  
  // Event listeners
  selectAllCheckbox.addEventListener('change', function() {
    toggleAll(this.checked);
  });
  
  itemCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      toggleSelection(parseInt(this.value), this.checked);
    });
  });
  
  bulkDeleteBtn.addEventListener('click', showBulkDeleteModal);
  
  // Initial update
  updateUI();
});
</script>
@endpush