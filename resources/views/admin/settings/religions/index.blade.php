@extends('layouts.admin')
@section('title','Tôn giáo')
@section('crumb','Cài đặt / Tôn giáo')

@section('content')
<style>[x-cloak]{display:none!important}</style>
<div class="space-y-6"
     x-data="relIdx({ baseUrl: '{{ route('admin.settings.religions.index') }}', qInit: @json(request('q','')), offInit: @json(request('is_official')) })"
     x-init="init()">
  <div class="flex items-center gap-3">
    <a href="{{ route('admin.settings.religions.create') }}" class="btn-primary">+ Thêm tôn giáo</a>
    
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
    <form @submit.prevent class="grid gap-3 md:grid-cols-12">
      <div class="md:col-span-5">
        <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
        <div class="relative">
          <input type="text" x-model="q" placeholder="Tên / mã tôn giáo…"
                 class="w-full rounded-lg border px-3 py-2 pr-9"
                 @input="debounce(()=>search(), 350)"
                 @keydown.enter.prevent="search()">
          <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                  x-show="q" @click="q=''; search()" x-cloak title="Xoá từ khoá">✕</button>
        </div>
      </div>

      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Trạng thái</label>
        <select x-model="off" @change="search()" class="w-full rounded-lg border px-3 py-2">
          <option value="">— Tất cả —</option>
          <option value="1">Chính thức</option>
          <option value="0">Khác</option>
        </select>
      </div>

      <div class="md:col-span-4 flex items-end gap-2">
        <button type="button" class="btn-primary" @click="search()">Lọc</button>
        <button type="button" class="btn-soft" @click="q=''; off=''; search()">Xoá lọc</button>
      </div>
    </form>
  </article>

  <article class="card overflow-hidden relative">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Danh sách tôn giáo</h2>
      <div class="text-sm text-slate-500" x-text="totalText"></div>
    </div>

    <div id="relTableWrap">
      @include('admin.settings.religions._table')
    </div>

    <div x-show="loading" x-transition class="absolute inset-0 bg-white/60 grid place-items-center" aria-live="polite">
      <div class="rounded-md border px-3 py-1 text-sm bg-white shadow">Đang tải…</div>
    </div>
  </article>
</div>

{{-- modal confirm xoá --}}
<div x-data="{ open:false, action:'', name:'' }"
     @confirm-delete.window="open=true; action=$event.detail.action; name=$event.detail.name">
  <div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>
    <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
      <h3 class="font-semibold text-lg">Xoá tôn giáo?</h3>
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
      Bạn chắc chắn muốn xoá <span class="font-medium" id="modal-selected-count">0</span> tôn giáo đã chọn?
      Hành động không thể hoàn tác.
    </p>

    <form action="{{ route('admin.settings.religions.bulk-destroy') }}" method="POST" class="mt-4 flex justify-end gap-2">
      @csrf
      <div id="hidden-inputs"></div>
      <button type="button" class="btn-soft" onclick="closeBulkDeleteModal()">Huỷ</button>
      <button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 text-white">Xoá</button>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('relIdx', (opts={}) => ({
      baseUrl: opts.baseUrl,
      q: opts.qInit ?? '',
      off: (opts.offInit ?? '') + '',
      loading: false,
      totalText: 'Tổng: {{ $religions->total() }}',
      _t: null,
      init(){ /* giữ trạng thái đầu vào từ URL */ },
      debounce(fn, ms=400){ clearTimeout(this._t); this._t = setTimeout(fn, ms); },
      search(){
        this.loading = true;
        const params = new URLSearchParams();
        if (this.q) params.set('q', this.q);
        if (this.off !== '') params.set('is_official', this.off);
        params.set('partial','1');
        fetch(`${this.baseUrl}?${params.toString()}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
          .then(r => r.text())
          .then(html => {
            document.getElementById('relTableWrap').innerHTML = html;
            const full = new URL(this.baseUrl, location.origin);
            if (this.q) full.searchParams.set('q', this.q); else full.searchParams.delete('q');
            if (this.off !== '') full.searchParams.set('is_official', this.off); else full.searchParams.delete('is_official');
            history.replaceState({}, '', full.toString());
            
            // Re-initialize bulk delete functionality after table reload
            initializeBulkDelete();
          })
          .finally(() => { this.loading = false; });
      }
    }))
  })

  // Bulk Delete JavaScript
  function initializeBulkDelete() {
    let selectedIds = [];
    
    // Elements
    const selectAllCheckbox = document.getElementById('select-all-checkbox');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const selectedCountSpan = document.getElementById('selected-count');
    const modal = document.getElementById('bulk-delete-modal');
    const modalSelectedCount = document.getElementById('modal-selected-count');
    const hiddenInputsDiv = document.getElementById('hidden-inputs');
    
    if (!selectAllCheckbox || !bulkDeleteBtn) return; // Exit if elements not found
    
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
    
    // Remove existing event listeners to prevent duplicates
    const newSelectAllCheckbox = selectAllCheckbox.cloneNode(true);
    selectAllCheckbox.parentNode.replaceChild(newSelectAllCheckbox, selectAllCheckbox);
    
    // Event listeners
    newSelectAllCheckbox.addEventListener('change', function() {
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
  }

  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
    initializeBulkDelete();
  });
</script>
@endpush
