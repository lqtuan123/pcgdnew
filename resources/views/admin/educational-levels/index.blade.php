@extends('layouts.admin')

@section('title', 'Quản lý bậc học')
@section('crumb', 'Bậc học')

@section('content')
<div class="space-y-6" id="bulk-delete-container">
  <!-- Toolbar -->
  <div class="flex items-center gap-3">
    <a href="{{ route('admin.educational-levels.create') }}" class="btn-primary">+ Thêm bậc học</a>
    
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

  <!-- Bảng danh sách -->
  <article class="card overflow-hidden">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Danh sách bậc học</h2>
      <div class="text-sm text-slate-500">Tổng: {{ $educationalLevels->total() }}</div>
    </div>

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
            <th class="th text-left">Tên bậc học</th>
            <th class="th text-left">Độ tuổi</th>
            <th class="th text-left">Số trường</th>
            <th class="th text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @forelse($educationalLevels as $i => $level)
            <tr class="hover:bg-slate-50">
              <td class="td">
                <input 
                  type="checkbox" 
                  class="item-checkbox rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                  value="{{ $level->id }}"
                  data-id="{{ $level->id }}"
                >
              </td>
              <td class="td">{{ $educationalLevels->firstItem() + $i }}</td>
              <td class="td">{{ $level->name }}</td>
              <td class="td">
                @if($level->age_min && $level->age_max)
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    {{ $level->age_min }} - {{ $level->age_max }} tuổi
                  </span>
                @elseif($level->age_min)
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    Từ {{ $level->age_min }} tuổi
                  </span>
                @elseif($level->age_max)
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                    Đến {{ $level->age_max }} tuổi
                  </span>
                @else
                  <span class="text-slate-400 text-sm">Chưa cập nhật</span>
                @endif
              </td>
              <td class="td">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                  {{ $level->schools_count }}
                </span>
              </td>
              <td class="td text-right">
                <a href="{{ route('admin.educational-levels.edit', $level) }}" class="text-amber-600 hover:underline mr-3">Sửa</a>
                
                @if($level->schools_count == 0)
                  <button type="button" class="text-rose-600 hover:underline"
                    @click="$dispatch('confirm-delete', { action: '{{ route('admin.educational-levels.destroy', $level) }}', name: @js($level->name) })">
                    Xoá
                  </button>
                @else
                  <span class="text-slate-400 cursor-not-allowed" title="Không thể xóa vì đang có trường học sử dụng">Xoá</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td class="td text-center" colspan="6">Không có dữ liệu.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">
      {{ $educationalLevels->withQueryString()->links() }}
    </div>
  </article>
</div>

<!-- Modal xác nhận xóa -->
<div x-data="{ open:false, action:'', name:'' }"
     @confirm-delete.window="
        open   = true;
        action = $event.detail.action;
        name   = $event.detail.name;
     ">
  <div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>

    <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
      <h3 class="font-semibold text-lg">Xoá bậc học?</h3>
      <p class="mt-2 text-sm text-slate-600">
        Bạn chắc chắn muốn xoá <span class="font-medium" x-text="name"></span>?
        Hành động không thể hoàn tác.
      </p>

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
      Bạn chắc chắn muốn xoá <span class="font-medium" id="modal-selected-count">0</span> bậc học đã chọn?
      Hành động không thể hoàn tác.
    </p>

    <form action="{{ route('admin.educational-levels.bulk-destroy') }}" method="POST" class="mt-4 flex justify-end gap-2">
      @csrf
      <div id="hidden-inputs"></div>
      <button type="button" class="btn-soft" onclick="closeBulkDeleteModal()">Huỷ</button>
      <button type="submit" class="px-3 py-2 rounded-lg bg-rose-600 text-white">Xoá</button>
    </form>
  </div>
</div>

@if(session('toast'))
  <div x-data="{ show: true }" x-show="show" x-transition class="fixed top-4 right-4 z-50">
    <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
      <div class="flex items-center gap-2">
        <i class="fas fa-check-circle"></i>
        <span>{{ session('toast') }}</span>
        <button @click="show = false" class="ml-4 text-white hover:text-green-100">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
  </div>
@endif

@if(session('error'))
  <div x-data="{ show: true }" x-show="show" x-transition class="fixed top-4 right-4 z-50">
    <div class="bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
      <div class="flex items-center gap-2">
        <i class="fas fa-exclamation-circle"></i>
        <span>{{ session('error') }}</span>
        <button @click="show = false" class="ml-4 text-white hover:text-red-100">
          <i class="fas fa-times"></i>
        </button>
      </div>
    </div>
  </div>
@endif

@endsection

@push('scripts')
<script>
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
