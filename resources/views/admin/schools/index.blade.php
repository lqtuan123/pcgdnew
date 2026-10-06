@extends('layouts.admin')

@section('title','Danh sách trường')
@section('crumb','Trường học')

@section('content')
  <style>[x-cloak]{display:none!important}</style>

  <div class="space-y-6" x-data="schoolIndex()" x-init="init()">
    <!-- Toolbar -->
    <div class="flex items-center gap-3">
      <a href="{{ route('admin.schools.create') }}" class="btn-primary">+ Thêm trường</a>
      <a href="{{ route('admin.schools.import.create') }}" class="btn-soft">Nhập từ Excel theo địa chính cũ</a>
      
      <!-- Quản lý loại hình và bậc học -->
      <div class="flex items-center gap-2 ml-4">
        <span class="text-sm text-slate-500">Quản lý:</span>
        <a href="{{ route('admin.school-types.index') }}" class="btn-soft text-sm">
          <i class="fas fa-school mr-1"></i> Loại hình trường
        </a>
        <a href="{{ route('admin.educational-levels.index') }}" class="btn-soft text-sm">
          <i class="fas fa-graduation-cap mr-1"></i> Bậc học
        </a>
      </div>
      
      <div class="ml-auto"></div>
    </div>
     

    <!-- Thống kê nhanh -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-blue-800">Loại hình trường</h3>
            <p class="text-2xl font-bold text-blue-600">{{ $types->count() ?? 0 }}</p>
          </div>
          <div class="text-blue-400">
            <i class="fas fa-school text-3xl"></i>
          </div>
        </div>
        <div class="mt-2">
          <a href="{{ route('admin.school-types.create') }}" class="text-blue-600 hover:text-blue-800 text-sm">
            <i class="fas fa-plus mr-1"></i>Thêm loại hình mới
          </a>
        </div>
      </div>

      <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-green-800">Bậc học</h3>
            <p class="text-2xl font-bold text-green-600">{{ $levels->count() ?? 0 }}</p>
          </div>
          <div class="text-green-400">
            <i class="fas fa-graduation-cap text-3xl"></i>
          </div>
        </div>
        <div class="mt-2">
          <a href="{{ route('admin.educational-levels.create') }}" class="text-green-600 hover:text-green-800 text-sm">
            <i class="fas fa-plus mr-1"></i>Thêm bậc học mới
          </a>
        </div>
      </div>

      <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-purple-800">Tổng trường</h3>
            <p class="text-2xl font-bold text-purple-600">{{ $schools->total() }}</p>
          </div>
          <div class="text-purple-400">
            <i class="fas fa-building text-3xl"></i>
          </div>
        </div>
        <div class="mt-2">
          <a href="{{ route('admin.schools.create') }}" class="text-purple-600 hover:text-purple-800 text-sm">
            <i class="fas fa-plus mr-1"></i>Thêm trường mới
          </a>
        </div>
      </div>
    </div>

    <!-- Bộ lọc -->
    <article class="card">
      <form id="schoolFilterForm" method="GET" action="{{ route('admin.schools.index') }}" class="grid gap-3 md:grid-cols-12">
        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
          <input
            type="text"
            name="q"
            value="{{ request('q') }}"
            placeholder="Mã / tên trường…"
            class="w-full rounded-lg border px-3 py-2"
            x-ref="q"
          >
        </div>

        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Loại hình</label>
          <select name="school_type_id" class="w-full rounded-lg border px-3 py-2">
            <option value="">— Tất cả —</option>
            @foreach(($types ?? []) as $t)
              <option value="{{ $t->id }}" @selected(request('school_type_id')==$t->id)>{{ $t->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Bậc học</label>
          <select name="educational_level_id" class="w-full rounded-lg border px-3 py-2">
            <option value="">— Tất cả —</option>
            @foreach(($levels ?? []) as $l)
              <option value="{{ $l->id }}" @selected(request('educational_level_id')==$l->id)>{{ $l->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Trạng thái</label>
          <select name="is_dissolved" class="w-full rounded-lg border px-3 py-2">
            <option value=""   @selected(request('is_dissolved','')==='')>— Tất cả —</option>
            <option value="0"  @selected(request('is_dissolved')==='0')>Chưa giải thể</option>
            <option value="1"  @selected(request('is_dissolved')==='1')>Đã giải thể</option>
          </select>
        </div>
        <!-- Địa phận (scheme=old) -->
        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Tỉnh (cũ)</label>
          <select class="w-full rounded-lg border px-3 py-2" @change="onChangeProv" x-ref="prov">
            <option value="">— Tất cả —</option>
            <template x-for="p in provinces" :key="'p'+p.id">
              <option :value="p.id" x-text="p.name"></option>
            </template>
          </select>
        </div>

        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Quận/Huyện (cũ)</label>
          <select class="w-full rounded-lg border px-3 py-2" @change="onChangeDist" x-ref="dist" :disabled="!districts.length">
            <option value="">— Tất cả —</option>
            <template x-for="d in districts" :key="'d'+d.id">
              <option :value="d.id" x-text="d.name"></option>
            </template>
          </select>
        </div>

        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Phường/Xã (cũ)</label>
          <select class="w-full rounded-lg border px-3 py-2" @change="onChangeWard" x-ref="ward" :disabled="!wards.length">
            <option value="">— Tất cả —</option>
            <template x-for="w in wards" :key="'w'+w.id">
              <option :value="w.id" x-text="w.name"></option>
            </template>
          </select>
        </div>
        <!-- Tỉnh (mới) -->
        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Tỉnh (mới)</label>
          <select class="w-full rounded-lg border px-3 py-2"
                  @change="onChangeProvNew"
                  x-ref="provNew">
            <option value="">— Tất cả —</option>
            <template x-for="p in provincesNew" :key="'np'+p.id">
              <option :value="p.id" x-text="p.name"></option>
            </template>
          </select>
        </div>

        <!-- Phường/Xã (mới) -->
        <div class="md:col-span-3">
          <label class="block text-xs text-slate-500 mb-1">Phường/Xã (mới)</label>
          <select class="w-full rounded-lg border px-3 py-2"
                  @change="onChangeWardNew"
                  x-ref="wardNew"
                  :disabled="!wardsNew.length">
            <option value="">— Tất cả —</option>
            <template x-for="w in wardsNew" :key="'nw'+w.id">
              <option :value="w.id" x-text="w.name"></option>
            </template>
          </select>
        </div>
        <div class="md:col-span-3 flex items-end gap-2">
          <button class="btn-primary">Lọc</button>
          <a href="{{ route('admin.schools.index') }}" class="btn-soft">Xoá lọc</a>
        </div>

        <!-- Hidden inputs gửi code về server -->
        <input type="hidden" name="province_code" value="{{ request('province_code') }}">
        <input type="hidden" name="district_code" value="{{ request('district_code') }}">
        <input type="hidden" name="ward_code" value="{{ request('ward_code') }}">
        <input type="hidden" name="province_code_new" value="{{ request('province_code_new') }}">
        <input type="hidden" name="ward_code_new"     value="{{ request('ward_code_new') }}">

      </form>
    </article>

    <!-- Bảng danh sách -->
    <article class="card overflow-hidden">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Danh sách trường</h2>
        <div class="flex items-center gap-2">
          <div class="text-sm text-slate-500">Tổng: {{ $schools->total() }}</div>
          
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
          
          <a href="{{ route('admin.schools.export', request()->query()) }}" class="btn-soft">
            Xuất Excel
          </a>
        </div>
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
              <th class="th text-left">Mã</th>
              <th class="th text-left">Tên trường</th>
              <th class="th text-left">Loại hình</th>
              <th class="th text-left">Bậc học</th>
            <th class="th text-left">Trạng thái</th>
              <th class="th text-left">Phường/Xã (cũ)</th>
              <th class="th text-left">Quận/Huyện (cũ)</th>
               <th>Phường/Xã (mới)</th>
              <th class="th text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            @forelse($schools as $i => $s)
              <tr class="hover:bg-slate-50">
                <td class="td">
                  <input 
                    type="checkbox" 
                    class="item-checkbox rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                    value="{{ $s->id }}"
                    data-id="{{ $s->id }}"
                  >
                </td>
                <td class="td">{{ $schools->firstItem() + $i }}</td>
                <td class="td font-mono">{{ $s->code }}</td>
                <td class="td">{{ $s->name }}</td>
                <td class="td">{{ optional($s->schoolType)->name }}</td>
                <td class="td">{{ optional($s->educationalLevel)->name }}</td>
                <td class="td">
                  @if($s->is_dissolved)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                      Đã giải thể
                    </span>
                  @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                      Hoạt động
                    </span>
                  @endif
                </td>
                {{-- Phường/Xã (mới) - HIỂN THỊ MÃ & TÊN --}}

                <td class="td">
                  @php $w = $s->relationLoaded('wardUnit') ? $s->wardUnit : null; @endphp
                  <div>{{ $w->name ?? '—' }}</div>
                  <div class="text-xs text-slate-400">{{ $s->ward_code ?: '—' }}</div>
                </td>
                <td class="td">
                  @php $d = $s->relationLoaded('districtUnit') ? $s->districtUnit : null; @endphp
                  <div>{{ $d->name ?? '—' }}</div>
                  <div class="text-xs text-slate-400">{{ $s->district_code ?: '—' }}</div>
                </td>
                <td>
                  @if($s->wardUnitNew)
                    <div>{{ $s->wardUnitNew->name }}</div>
                    <div class="text-xs text-slate-500">{{ $s->ward_code_new }}</div>
                  @else
                    {{-- nếu chưa map ra tên (thiếu mapping) thì vẫn show mã để dễ debug --}}
                    @if(!empty($s->ward_code_new))
                      <div class="text-xs">{{ $s->ward_code_new }}</div>
                    @else
                      <div class="text-xs text-slate-400">—</div>
                    @endif
                  @endif
                </td>
                <td class="td text-right">
                  <a href="{{ route('admin.schools.show',$s) }}" class="text-blue-600 hover:underline mr-3">Xem</a>
                  <a href="{{ route('admin.schools.edit',$s) }}" class="text-amber-600 hover:underline mr-3">Sửa</a>
                  <button type="button"
                          class="text-rose-600 hover:underline"
                          @click="$dispatch('confirm-delete', {
                            action: '{{ route('admin.schools.destroy',$s) }}',
                            name:   @js($s->name)
                          })">
                    Xoá
                  </button>
                </td>
              </tr>
            @empty
              <tr><td class="td text-center" colspan="11">Không có dữ liệu.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $schools->withQueryString()->links() }}
      </div>
    </article>
  </div>
{{-- modal --}}
  <div x-data="{ open:false, action:'', name:'' }"
     @confirm-delete.window="
        open   = true;
        action = $event.detail.action;
        name   = $event.detail.name;
     ">
  <div x-show="open" x-transition class="fixed inset-0 z-[70] grid place-items-center">
    <div class="absolute inset-0 bg-black/40" @click="open=false"></div>

    <div class="relative bg-white rounded-xl p-5 w-[420px] shadow-xl">
      <h3 class="font-semibold text-lg">Xoá trường?</h3>
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
      Bạn chắc chắn muốn xoá <span class="font-medium" id="modal-selected-count">0</span> trường học đã chọn?
      Hành động không thể hoàn tác.
    </p>

    <form action="{{ route('admin.schools.bulk-destroy') }}" method="POST" class="mt-4 flex justify-end gap-2">
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
window.schoolIndex = function () {
  const R = {
    provinces   : @json(route('admin.geo.provinces')), // ?scheme=old|new
    childrenBase: @json(url('admin/geo/children')),   // /{parentId}
  };

  return {
    // ====== CŨ (3 cấp: 1→2→3) ======
    provinces: [], districts: [], wards: [],
    sel: { prov:null, dist:null, ward:null },
    initial: {
      provCode: @json(request('province_code')),
      distCode: @json(request('district_code')),
      wardCode: @json(request('ward_code')),
    },

    // ====== MỚI (2 cấp: 1→3) ======
    provincesNew: [], wardsNew: [],
    selNew: { prov:null, ward:null },
    initialNew: {
      provCode: @json(request('province_code_new')),
      wardCode: @json(request('ward_code_new')),
    },

    async init(){
      // nạp tỉnh cũ & mới song song
      await Promise.all([ this.loadProvincesOld(), this.loadProvincesNew() ]);

      // ---- preselect OLD theo query ----
      if (this.initial.provCode) {
        const p = this.provinces.find(x => x.code === this.initial.provCode);
        if (p) {
          this.sel.prov = p;
          this.$refs?.prov && (this.$refs.prov.value = String(p.id));
          await this.loadDistrictsOld(p.id);
        }
      }
      if (this.initial.distCode) {
        const d = this.districts.find(x => x.code === this.initial.distCode);
        if (d) {
          this.sel.dist = d;
          this.$refs?.dist && (this.$refs.dist.value = String(d.id));
          await this.loadWardsOld(d.id);
        }
      }
      if (this.initial.wardCode) {
        const w = this.wards.find(x => x.code === this.initial.wardCode);
        if (w) {
          this.sel.ward = w;
          this.$refs?.ward && (this.$refs.ward.value = String(w.id));
        }
      }

      // ---- preselect NEW theo query (2 cấp) ----
      if (this.initialNew.provCode) {
        const pN = this.provincesNew.find(x => x.code === this.initialNew.provCode);
        if (pN) {
          this.selNew.prov = pN;
          this.$refs?.provNew && (this.$refs.provNew.value = String(pN.id));
          await this.loadWardsNew(pN.id); // cấp 3 trực tiếp dưới tỉnh mới
        }
      }
      if (this.initialNew.wardCode) {
        const wN = this.wardsNew.find(x => x.code === this.initialNew.wardCode);
        if (wN) {
          this.selNew.ward = wN;
          this.$refs?.wardNew && (this.$refs.wardNew.value = String(wN.id));
        }
      }

      this.syncHidden();
    },

    // ========= Loaders OLD =========
    async loadProvincesOld(){
      const res = await fetch(`${R.provinces}?scheme=old`, { headers:{Accept:'application/json'} });
      this.provinces = res.ok ? await res.json() : [];
    },
    async loadDistrictsOld(parentId){
      this.districts=[]; this.wards=[]; this.sel.dist=null; this.sel.ward=null;
      this.$refs?.dist && (this.$refs.dist.value='');
      this.$refs?.ward && (this.$refs.ward.value='');
      if(!parentId) return;
      const res = await fetch(`${R.childrenBase}/${parentId}`, { headers:{Accept:'application/json'} });
      const json = res.ok ? await res.json() : {children:[]};
      this.districts = json.children || [];
    },
    async loadWardsOld(parentId){
      this.wards=[]; this.sel.ward=null;
      this.$refs?.ward && (this.$refs.ward.value='');
      if(!parentId) return;
      const res = await fetch(`${R.childrenBase}/${parentId}`, { headers:{Accept:'application/json'} });
      const json = res.ok ? await res.json() : {children:[]};
      this.wards = json.children || [];
    },

    // ========= Loaders NEW (KHÔNG có cấp 2) =========
    async loadProvincesNew(){
      const res = await fetch(`${R.provinces}?scheme=new`, { headers:{Accept:'application/json'} });
      this.provincesNew = res.ok ? await res.json() : [];
    },
    async loadWardsNew(parentId){ // lấy cấp 3 mới trực tiếp từ tỉnh mới
      this.wardsNew=[]; this.selNew.ward=null;
      this.$refs?.wardNew && (this.$refs.wardNew.value='');
      if(!parentId) return;
      const res = await fetch(`${R.childrenBase}/${parentId}`, { headers:{Accept:'application/json'} });
      const json = res.ok ? await res.json() : {children:[]};
      this.wardsNew = json.children || [];
    },

    // ========= Handlers OLD =========
    onChangeProv(e){
      const id = Number(e.target.value||0);
      this.sel.prov = this.provinces.find(x=>x.id===id) || null;
      this.syncHidden();
      this.loadDistrictsOld(id);
    },
    onChangeDist(e){
      const id = Number(e.target.value||0);
      this.sel.dist = this.districts.find(x=>x.id===id) || null;
      this.syncHidden();
      this.loadWardsOld(id);
    },
    onChangeWard(e){
      const id = Number(e.target.value||0);
      this.sel.ward = this.wards.find(x=>x.id===id) || null;
      this.syncHidden();
    },

    // ========= Handlers NEW (2 cấp) =========
    onChangeProvNew(e){
      const id = Number(e.target.value||0);
      this.selNew.prov = this.provincesNew.find(x=>x.id===id) || null;
      this.syncHidden();
      this.loadWardsNew(id);  // nạp luôn cấp 3
    },
    onChangeWardNew(e){
      const id = Number(e.target.value||0);
      this.selNew.ward = this.wardsNew.find(x=>x.id===id) || null;
      this.syncHidden();
    },

    // ========= Helpers =========
    syncHidden(){
      const f = document.getElementById('schoolFilterForm');
      if(!f) return;
      // OLD
      f.querySelector('input[name="province_code"]').value = this.sel.prov?.code || '';
      f.querySelector('input[name="district_code"]').value = this.sel.dist?.code || '';
      f.querySelector('input[name="ward_code"]').value     = this.sel.ward?.code || '';
      // NEW (chỉ province & ward)
      const pN = f.querySelector('input[name="province_code_new"]');
      const wN = f.querySelector('input[name="ward_code_new"]');
      if (pN) pN.value = this.selNew.prov?.code || '';
      if (wN) wN.value = this.selNew.ward?.code || '';
    },
    submitForm(){
      document.getElementById('schoolFilterForm')?.submit();
    },
  };
};

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
