@extends('layouts.front')

@section('title', 'Kiểm tra dữ liệu')

@section('content')
<div x-data="bulkSelect()" 
     @open-detail.window="if ($store.detailModal) { $store.detailModal.openWith($event.detail) }"
     @soft-delete-one.window="if (window.detailUIInstance?.softDelete) { detailUIInstance.softDelete($event.detail) }"
     class="mx-auto max-w-7xl px-4 py-6">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Kiểm tra dữ liệu</h1>
                <p class="mt-2 text-slate-600">Kiểm tra và phát hiện các lỗi dữ liệu trong hệ thống</p>
            </div>
        </div>

        <!-- Filters -->
        <form id="dataValidationForm" method="get" class="mb-6 bg-white border border-slate-200 rounded-lg p-6">
            <div class="grid gap-3 md:grid-cols-12">
                <!-- Năm học -->
                <div class="md:col-span-4">
                    <label class="block text-xs text-slate-500 mb-1">Năm học</label>
                    <select name="academic_year" class="w-full rounded-lg border px-3 py-2">
                        <option value="all" @selected($academicYear==='all')>— Tất cả —</option>
                        @foreach($academicYears as $yy)
                            <option value="{{ $yy }}" @selected($yy==$academicYear)>{{ $yy }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Khu vực -->
                <div class="md:col-span-8">
                    <label class="block text-xs text-slate-500 mb-1">Khu vực</label>
                    @include('partials.front.geo-filter', [
                        'scheme' => $scheme ?? 'old',
                        'geo' => $geoData ?? [],
                        'showVillage' => true
                    ])
                </div>

                <!-- Tiêu chí -->
                <div class="md:col-span-12">
                    <label class="block text-xs text-slate-500 mb-1">Tiêu chí <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <select id="criteria" name="criteria" required
                            class="flex-1 rounded-lg border px-3 py-2">
                            <option value="">Chọn tiêu chí</option>
                            @foreach($criteria as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="button" id="showCriteriaDescription" 
                            class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg border hover:bg-slate-200 transition-colors"
                            title="Xem mô tả tiêu chí">
                            📋 Mô tả
                        </button>
                    </div>
                    <!-- Mô tả tiêu chí -->
                    <div id="criteriaDescription" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg hidden">
                        <div class="text-sm text-blue-800">
                            <strong>Mô tả:</strong> <span id="criteriaDescriptionText"></span>
                        </div>
                    </div>
                </div>

                <!-- Button kiểm tra -->
                <div class="md:col-span-12 flex justify-end">
                    <button id="checkButton" type="button"
                        class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-semibold rounded-xl hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span id="checkButtonText">Kiểm tra dữ liệu</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Results -->
        <div id="resultsContainer" class="hidden">
            <!-- Bulk Toolbar -->
            <div id="bulkToolbar" class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between rounded-xl border bg-slate-50 p-3">
                <div class="text-sm">
                    <div class="font-medium">
                        Tổng số đối tượng: <span id="totalCount" class="tabular-nums">0</span>
                    </div>
                    <div class="text-slate-600">
                        Hiển thị: <span id="fromCount" class="tabular-nums">0</span>–<span id="toCount" class="tabular-nums">0</span>
                        <template x-if="selectedCount>0">
                            <span class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-amber-800">Đã chọn: <b x-text="selectedCount"></b></span>
                        </template>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                        class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="selectedCount===0"
                        x-text="selectedCount>0 ? `Xóa tạm (${selectedCount})` : 'Xóa tạm'"
                        @click="confirmBulkDelete()"></button>

                    <button type="button"
                        class="rounded-lg bg-red-800 px-3 py-2 text-white hover:bg-red-900 disabled:opacity-50 disabled:cursor-not-allowed"
                        :disabled="selectedCount===0"
                        x-text="selectedCount>0 ? `Xóa (${selectedCount})` : 'Xóa'"
                        @click="confirmBulkHardDelete()"></button>
                </div>
            </div>

            <!-- Pagination Controls -->
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-600">Hiển thị:</label>
                    <select id="perPageSelect" class="rounded border px-2 py-1 text-sm">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-sm text-slate-600">đối tượng/trang</span>
                </div>
                <div id="paginationInfo" class="text-sm text-slate-600">
                    <!-- Pagination info will be populated here -->
                </div>
            </div>

            <!-- Desktop Table -->
            <div id="desktopTable" class="hidden md:block">
                <div class="relative">
                    <div class="overflow-x-auto overflow-y-auto max-h-[600px] border border-slate-200 rounded-lg">
                        <table class="min-w-[2000px]" id="data-validation-table">
                            <thead class="sticky top-0 z-40">
                                <tr class="bg-slate-100">
                                    <th class="th sticky left-0 z-50 bg-slate-100 w-10" rowspan="2">
                                        <input type="checkbox" @change="toggleAll($event)" aria-label="Chọn tất cả">
                                    </th>
                                    <th class="th sticky left-10 z-50 bg-slate-100 w-10" rowspan="2"></th>
                                    <th class="th sticky left-20 z-40 bg-slate-100 w-12 border-slate-600" rowspan="2">#</th>
                                    <!-- Dynamic group headers will be populated here -->
                                </tr>
                                <tr class="bg-slate-50">
                                    <!-- Dynamic column headers will be populated here -->
                                </tr>
                            </thead>
                            <tbody id="tableBody">
                                <!-- Results will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="paginationContainer" class="mt-3 flex justify-center">
                    <!-- Pagination buttons will be populated here -->
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div id="loadingContainer" class="hidden">
            <div class="bg-white border border-slate-200 rounded-lg p-12">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">
                        <svg class="w-8 h-8 text-blue-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-800 mb-2">Đang kiểm tra dữ liệu...</h3>
                    <p class="text-slate-600">Vui lòng chờ trong giây lát</p>
                </div>
            </div>
        </div>

        <!-- No results -->
        <div id="noResultsContainer" class="hidden">
            <div class="bg-white border border-slate-200 rounded-lg p-12">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-800 mb-2">Không phát hiện lỗi</h3>
                    <p class="text-slate-600">Dữ liệu đã được kiểm tra và không có lỗi nào được phát hiện.</p>
                </div>
            </div>
        </div>
    </div>
    
    {{-- Modal chi tiết dùng chung --}}
    @include('front.surveys._modal-detail')
</div>

<script>
/** ========== GLOBAL: schoolPicker – dùng trong modal ========== */
window.schoolPicker = window.schoolPicker || function (opts = {}) {
  return {
    // OPTIONS
    inputName:  opts.inputName  || 'education[school_id]',
    withGeo:   !!opts.withGeo,
    scheme:     opts.schemeInit || 'new',
    geo:        Object.assign({province_id:null, district_id:null, ward_id:null}, opts.geoInit || {}),
    mirror:     Object.assign({}, opts.mirror || {}),
    initial:    opts.initial || null,
    endpoints:  Object.assign({ search: '/truong/datapicker' }, opts.endpoints || {}),
    perPage:    Number(opts.perPage || 10),

    // STATE
    q:'', open:false, loading:false,
    results:[], picked:null,
    page:1, hasMore:false,

    // LIFECYCLE
    init(){ this._boot(); },
    boot(){ this._boot(); },
    _boot(){
      if (this.withGeo) { this._syncGeoFromDom(); this._bindGeoDomListeners(); }
      if (this.initial) { this.picked = this.initial; this.q = this.initial.name || ''; }
      const inp = this.$root.querySelector('input[type="text"]');
      if (inp) inp.addEventListener('focus', () => { this.open = true; });
    },

    // GEO helpers: đọc các select tên geo_school[...]
    _syncGeoFromDom(){
      const read = (name) => {
        const el = this.$root.querySelector(`[name="${name}"]`);
        if (!el) return null;
        const v = (el.value || '').trim();
        if (v === '') return null;
        const n = Number(v);
        return Number.isFinite(n) ? n : v;
      };
      this.geo.province_id = read('geo_school[province_id]');
      this.geo.district_id = read('geo_school[district_id]');
      this.geo.ward_id     = read('geo_school[ward_id]');
    },
    _bindGeoDomListeners(){
      ['province_id','district_id','ward_id'].forEach(k => {
        const el = this.$root.querySelector(`[name="geo_school[${k}]"]`);
        if (!el) return;
        el.addEventListener('change', () => {
          this._syncGeoFromDom();
          if ((this.q||'').trim().length >= 2) this.search(true);
        });
      });
    },

    // SEARCH
    async search(reset = true){
      const q = (this.q||'').trim();
      if (q.length < 2) { this.results=[]; this.hasMore=false; this.open=!!q; return; }
      if (reset) this.page = 1; else this.page += 1;
      if (this.withGeo) this._syncGeoFromDom();

      const p = new URLSearchParams({ q, page:String(this.page), per_page:String(this.perPage) });
      if (this.geo.province_id) p.set('province_code_new', this.geo.province_id);
      if (this.geo.district_id) p.set('district_code_new', this.geo.district_id);
      if (this.geo.ward_id)     p.set('ward_code_new',     this.geo.ward_id);

      this.loading = true; this.open = true;
      try {
        const res = await fetch(`${this.endpoints.search}?${p.toString()}`, {
          headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }
        });
        const data = await res.json();
        const raw = Array.isArray(data) ? data : (data.data || data.items || []);
        const items = raw.map(d => ({
          id: d.id, code: d.code, name: d.name, ward_new: d.ward_new, province_new: d.province_new
        }));
        this.results = reset ? items : this.results.concat(items);

        const current = data.current_page ?? this.page;
        const last    = data.last_page ?? (items.length === this.perPage ? current+1 : current);
        this.hasMore  = Number.isFinite(last) ? (current < last) : (items.length >= this.perPage);
      } catch(e){
        console.error('schoolPicker.search', e);
        if (reset) this.results = [];
        this.hasMore = false;
      } finally {
        this.loading = false; this.open = true;
      }
    },
    loadMore(){ if (this.hasMore) this.search(false); },

    // PICK/CLEAR
    pick(r){ this.picked=r; this.q=r?.name||''; this.results=[]; this.open=false; },
    clearPick(){ this.picked=null; this.q=''; this.results=[]; this.open=false; },
  };
};
</script>

{{-- MODAL AJAX DÙNG CHUNG --}}
<div x-data
     class="fixed inset-0 z-[9999] grid place-items-center bg-black/40"
     x-show="$store.appModal?.open"
     x-cloak
     @click.self.stop="$store.appModal.onBackdrop()"
     @keydown.escape.window="$store.appModal.onEsc()"  data-ajax 
     role="dialog" aria-modal="true">
  <div class="w-[min(900px,92vw)] max-h-[88vh] overflow-y-auto rounded-2xl bg-white shadow-2xl" @click.stop>
    <div class="flex items-center justify-between px-4 py-3 border-b">
      <h3 id="modal-title" class="font-medium" x-text="$store.appModal.title"></h3>
      <button class="h-8 w-8 grid place-items-center rounded-lg hover:bg-slate-100"
              @click="$store.appModal.close()">✕</button>
    </div>
    <div class="p-4">
      <template x-if="$store.appModal.loading"><div class="text-sm text-slate-500">Đang tải…</div></template>
      <div id="app-modal-body"></div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
  try { if (Alpine.store('appModal')) return; } catch(e){}
  Alpine.store('appModal', {
    open:false, loading:false, title:'',
    backdropClosable:false, escClosable:true,
    onBackdrop(){ if (this.backdropClosable) this.close(); },
    onEsc(){ if (this.escClosable) this.close(); },

    async showFrom(url, {title=''}={}) {
      this.open = true; this.loading = true; this.title = title;
      try {
        const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' }});
        const html = await res.text();
        this.setHtml(html);
      } catch {
        this.setHtml('<div class="text-red-600">Không tải được nội dung.</div>');
      } finally { this.loading = false; }
    },

    setHtml(html){
      const body = document.getElementById('app-modal-body');
      if (!body) return;
      if (window.Alpine && Alpine.destroyTree) Alpine.destroyTree(body);
      body.innerHTML = html;
      if (window.Alpine && Alpine.initTree) Alpine.initTree(body);
      const af = body.querySelector('[autofocus]') || body.querySelector('button, input, select, textarea, [tabindex]');
      if (af) af.focus({preventScroll:true});
      // bind close button inside modal content
      body.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => { e.preventDefault(); this.close(); });
      });
      
      this.bindAjaxForms(); // NEW 👈 intercept các form data-ajax trong nội dung vừa chèn
    },

    bindAjaxForms(){
      const body = document.getElementById('app-modal-body');
      if (!body) return;

      body.querySelectorAll('form[data-ajax]').forEach(form => {
        // tránh gắn lặp lại nhiều lần
        if (form._ajaxBound) return;
        form._ajaxBound = true;

        form.addEventListener('submit', async (ev) => {
          ev.preventDefault();

          const method = (form.method || 'POST').toUpperCase();
          const action = form.action || location.href;
          const formData = new FormData(form);

          try {
            const res = await fetch(action, {
              method,
              body: formData,
              headers: { 'X-Requested-With':'XMLHttpRequest' }
            });

            if (res.ok) {
              const contentType = res.headers.get('content-type');
              if (contentType && contentType.includes('application/json')) {
                // Xử lý JSON response từ controller
                const data = await res.json();
                if (data.ok) {
                  // Hiển thị thông báo thành công và đóng modal
                  alert('Đã cập nhật thành công!');
                  this.close();
                  // Reload danh sách kiểm tra dữ liệu
                  if (window.reloadDataValidationResults) {
                    window.reloadDataValidationResults();
                  }
                } else {
                  alert('Lỗi: ' + (data.message || 'Không thể cập nhật'));
                }
              } else {
                // Xử lý HTML response như cũ
                const html = await res.text();
                this.setHtml(html);
              }
            } else {
              this.setHtml('<div class="text-red-600">Lỗi: ' + res.status + '</div>');
            }
          } catch (err) {
            this.setHtml('<div class="text-red-600">Lỗi kết nối: ' + err.message + '</div>');
          }
        });
      });
    },

    close(){ this.open = false; this.loading = false; this.title = ''; }
  });
});

function detailUI(){
  return {
    open:false,
    item:null,
     // Có dữ liệu địa bàn cũ/mới?
    hasOld(r){ return !!(r?.old_province_name || r?.old_district_name || r?.old_ward_name || r?.village_name); },
    hasNew(r){ return !!(r?.new_province_name || r?.new_ward_name || r?.village_name_new); /* nếu có thôn mới */ },

    // Chuỗi địa bàn cũ: Tỉnh · Huyện · Xã · Thôn
    locOld(r){
      if(!r) return '';
      const parts = [
        r.old_province_name,
        r.old_district_name,
        r.old_ward_name,
        r.village_name,          // thôn/ấp (cũ)
      ];
      return parts.filter(v => !!(v && String(v).trim())).join(' · ');
    },

    // Chuỗi địa bàn mới: Tỉnh · Xã · (Thôn nếu có)
    locNew(r){
      if(!r) return '';
      const parts = [
        r.new_province_name,
        r.new_ward_name,
        r.village_name_new       // nếu có trường thôn/ấp mới, không có thì bỏ
      ];
      return parts.filter(v => !!(v && String(v).trim())).join(' · ');
    },

    disabilityKeys: [
      {key:'dis_van_dong', label:'Vận động'},
      {key:'dis_nghe_noi', label:'Nghe/Nói'},
      {key:'dis_nhin',     label:'Nhìn'},
      {key:'dis_than_kinh',label:'Thần kinh'},
      {key:'dis_tri_tue',  label:'Trí tuệ'},
      {key:'dis_hoc_tap',  label:'Học tập'},
      {key:'dis_tu_ky',    label:'Tự kỷ'},
      {key:'dis_khac',     label:'Khác'},
    ],
    boot(){},
    openWith(r){ this.item = r; this.open = true; },
    close(){ this.open = false; this.item = null; },
    fullName(r){ if(!r) return ''; return [r.ho_dem||'', r.ten||''].join(' ').trim(); },
    dob(r){
      if(!r) return '';
      const dd = [r.dob_day, r.dob_month, r.dob_year].filter(Boolean).join('/');
      return dd || '—';
    },

    // Hàm xóa tạm đối tượng
    softDelete(id) {
      // console.log('softDelete called with id:', id);
      
      if (!id || id === 'null') {
        alert('Không tìm thấy ID đối tượng: ' + id);
        return;
      }

      const message = 'Bạn có muốn xóa tạm đối tượng?\nĐể phục hồi đối tượng vào Điều tra -> Danh sách xóa tạm.';
      
      if (confirm(message)) {
        // console.log('User confirmed, sending request to:', `/doituong-dieutra/${id}/soft-delete`);
        
        // Gửi request xóa tạm
        fetch(`/doituong-dieutra/${id}/soft-delete`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({ id: id })
        })
        .then(response => {
          // console.log('Response status:', response.status);
          return response.json();
        })
        .then(data => {
          // console.log('Response data:', data);
          if (data.success) {
            alert('Đã xóa tạm đối tượng thành công');
            // Reload danh sách kiểm tra dữ liệu
            reloadDataValidationResults();
          } else {
            alert('Lỗi: ' + (data.message || 'Không thể xóa tạm đối tượng'));
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Lỗi kết nối: Không thể xóa tạm đối tượng');
        });
      }
    },
  }
}

document.addEventListener('DOMContentLoaded', function() {
    const checkButton = document.getElementById('checkButton');
    const checkButtonText = document.getElementById('checkButtonText');
    const resultsContainer = document.getElementById('resultsContainer');
    const loadingContainer = document.getElementById('loadingContainer');
    const noResultsContainer = document.getElementById('noResultsContainer');
    const totalCount = document.getElementById('totalCount');
    
    // Mô tả tiêu chí
    const criteriaDescriptions = {
        'duplicate_pcgd': 'Tìm những người có cùng Họ đệm, tên, ngày tháng năm sinh trong cùng năm học và khu vực đã chọn.',
        'invalid_birth_date': 'Tìm những người có ngày sinh, tháng sinh, năm sinh không hợp lệ (năm < 1900, năm > năm hiện tại, tháng < 1 hoặc > 12, ngày < 1 hoặc > 31, hoặc thiếu thông tin).',
        'invalid_graduation_year': 'Tìm những người có năm tốt nghiệp không phù hợp với năm sinh: MN ≥ 5 tuổi, TH ≥ 10 tuổi, THCS ≥ 14 tuổi, THPT ≥ 17 tuổi.',
          'invalid_class_age': 'Tìm những người có khối học không phù hợp với tuổi: Khối 3-12 tháng (0-1 tuổi), Khối 13-24 tháng (1-2 tuổi), Khối 25-36 tháng (2-3 tuổi), Khối 3-4 tuổi (3-4 tuổi), Khối 4-5 tuổi (4-5 tuổi), Khối 5-6 tuổi (5-6 tuổi), Khối 1 (6-7 tuổi), Khối 2 (7-8 tuổi), Khối 3 (8-9 tuổi), Khối 4 (9-10 tuổi), Khối 5 (10-11 tuổi), Khối 6 (11-12 tuổi), Khối 7 (12-13 tuổi), Khối 8 (13-14 tuổi), Khối 9 (14-15 tuổi), Khối 10 (15-16 tuổi), Khối 11 (16-17 tuổi), Khối 12 (17-18 tuổi).',
        'invalid_graduation_class': 'Tìm những người có bậc tốt nghiệp không phù hợp với lớp đang học/bỏ học: Đã tốt nghiệp bậc cao nhưng vẫn đang học/bỏ học bậc thấp hơn.',
        'missing_study_info': 'Tìm những người thiếu thông tin học tập: Có khối học nhưng không có tên trường, có tên trường nhưng không có khối học, hoặc có mã trường sai/không tồn tại.',
        'ppc_missing_study_info': 'Tìm những người từ 3 tháng - 18 tuổi nhưng thiếu thông tin học tập: không có khối học, mã trường, tên trường.',
        'special_circumstance': 'Tìm những đối tượng có hoàn cảnh đặc biệt: những người có thông tin hoàn cảnh đặc biệt.',
        'logic_errors': 'Tìm những người có lỗi logic khác (ngày sinh trong tương lai, tuổi quá lớn để học lớp thông thường, năm tốt nghiệp lớn hơn năm hiện tại).'
    };
    
    // Event listener cho nút mô tả tiêu chí
    const showCriteriaDescriptionBtn = document.getElementById('showCriteriaDescription');
    const criteriaDescriptionDiv = document.getElementById('criteriaDescription');
    const criteriaDescriptionText = document.getElementById('criteriaDescriptionText');
    const criteriaSelect = document.getElementById('criteria');
    
    showCriteriaDescriptionBtn.addEventListener('click', function() {
        const selectedCriteria = criteriaSelect.value;
        
        if (!selectedCriteria) {
            alert('Vui lòng chọn tiêu chí trước khi xem mô tả.');
            return;
        }
        
        // Nếu đang hiển thị thì ẩn đi, nếu đang ẩn thì hiển thị
        if (criteriaDescriptionDiv.classList.contains('hidden')) {
            const description = criteriaDescriptions[selectedCriteria];
            if (description) {
                criteriaDescriptionText.textContent = description;
            } else {
                criteriaDescriptionText.textContent = 'Không có mô tả cho tiêu chí này.';
            }
            criteriaDescriptionDiv.classList.remove('hidden');
        } else {
            criteriaDescriptionDiv.classList.add('hidden');
        }
    });
    
    // Ẩn mô tả khi thay đổi tiêu chí
    criteriaSelect.addEventListener('change', function() {
        criteriaDescriptionDiv.classList.add('hidden');
    });
    
    // Kiểm tra xem các element có tồn tại không
    if (!checkButton || !checkButtonText || !resultsContainer || !loadingContainer || !noResultsContainer || !totalCount) {
        console.error('Một số element cần thiết không tồn tại');
        return;
    }

    checkButton.addEventListener('click', function() {
        const form = document.querySelector('form');
        if (!form) {
            console.error('Không tìm thấy form');
            alert('Có lỗi xảy ra: Không tìm thấy form');
            return;
        }
        const formData = new FormData(form);
        
        let academicYear = formData.get('academic_year');
        let criteria = formData.get('criteria');
        let scheme = formData.get('scheme');
        
        // Debug: Log các giá trị để kiểm tra
        const criteriaSelect = document.getElementById('criteria');
        const academicYearSelect = document.querySelector('select[name="academic_year"]');
        const schemeSelect = document.querySelector('select[name="scheme"]');
        
        // Fallback: Nếu FormData không lấy được giá trị, thử lấy trực tiếp từ select
        if (!academicYear && academicYearSelect) {
            academicYear = academicYearSelect.value;
        }
        if (!criteria && criteriaSelect) {
            criteria = criteriaSelect.value;
        }
        if (!scheme && schemeSelect) {
            scheme = schemeSelect.value;
        }
        
        // Nếu vẫn null, set giá trị mặc định
        if (!academicYear) academicYear = 'all';
        if (!scheme) scheme = 'old';
        // console.log('Form data:', {
        //     academicYear,
        //     criteria,
        //     scheme,
        //     criteriaSelectValue: criteriaSelect ? criteriaSelect.value : 'null',
        //     formData: Array.from(formData.entries())
        // });
        
        // Lấy geo data từ form
        const geoData = {
            province_id: formData.get('geo[province_id]'),
            district_id: formData.get('geo[district_id]'),
            ward_id: formData.get('geo[ward_id]'),
            village_id: formData.get('geo[village_id]')
        };

        if (!criteria || criteria === '') {
            alert('Vui lòng chọn tiêu chí kiểm tra');
            // console.log('Criteria value:', criteria);
            return;
        }

        // Hiển thị loading
        showLoading();

        // Lấy per page từ select
        const perPageSelect = document.getElementById('perPageSelect');
        const perPage = perPageSelect ? perPageSelect.value : '10';

        // Gửi request kiểm tra
        const params = new URLSearchParams({
            academic_year: academicYear || '',
            criteria: criteria,
            scheme: scheme || 'old',
            per_page: perPage,
            page: '1', // Reset về trang đầu
            'geo[province_id]': geoData.province_id || '',
            'geo[district_id]': geoData.district_id || '',
            'geo[ward_id]': geoData.ward_id || '',
            'geo[village_id]': geoData.village_id || ''
        });

        fetch('{{ route("front.data-validation.check") }}?' + params.toString(), {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            hideLoading();
            
            // Debug: Log data để kiểm tra
            // console.log('Data received:', data);
            // console.log('Groups:', data.groups);
            // console.log('Columns:', data.columns);
            
            if (data.success) {
                if (data.total > 0) {
                    showResults(data);
                } else {
                    showNoResults();
                }
            } else {
                alert('Có lỗi xảy ra: ' + (data.error || 'Không thể kiểm tra dữ liệu'));
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi kiểm tra dữ liệu: ' + error.message);
        });
    });

    function showLoading() {
        checkButton.disabled = true;
        checkButtonText.innerHTML = 'Đang kiểm tra...';
        
        resultsContainer.classList.add('hidden');
        noResultsContainer.classList.add('hidden');
        loadingContainer.classList.remove('hidden');
    }

    function hideLoading() {
        checkButton.disabled = false;
        checkButtonText.innerHTML = 'Kiểm tra dữ liệu';
        
        loadingContainer.classList.add('hidden');
    }

    function showResults(data) {
        try {
            const resultsContainer = document.getElementById('resultsContainer');
            const desktopTable = document.getElementById('desktopTable');
            const totalCount = document.getElementById('totalCount');
            const fromCount = document.getElementById('fromCount');
            const toCount = document.getElementById('toCount');

            // Cập nhật thông tin tổng số
            totalCount.textContent = data.total;
            fromCount.textContent = data.pagination.from || 0;
            toCount.textContent = data.pagination.to || 0;

            // Tạo table header động
            createTableHeaders(data.groups, data.columns);

        // Tạo bảng kết quả
        const tableBody = document.getElementById('tableBody');
        tableBody.innerHTML = '';

        data.rows.forEach((row, index) => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-slate-50';
            
            // Xử lý an toàn JSON.stringify
            let rowData;
            try {
                rowData = JSON.stringify(row);
            } catch (e) {
                console.error('Error stringifying row:', e, row);
                rowData = '{}';
            }
            tr.setAttribute('x-data', `{ row: ${rowData} }`);
            
            // Tạo các cột động
            let rowHtml = `
                <td class="td sticky left-0 z-20 bg-white w-10">
                    <input type="checkbox" class="row-checkbox" name="ids[]" :value="row.detail_id"
                           @change="onRowToggle()" :aria-label="'Chọn đối tượng ' + row.ten">
                </td>
                <td class="td sticky left-10 z-20 bg-white w-10">
                    <div x-data="kebabMenu()" x-init="init()" @keydown.escape.window="close()">
                        <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg hover:bg-slate-100"
                                @click="openMenu($event)" aria-label="Mở menu">⋮</button>
                        <template x-teleport="body">
                            <div x-show="open" x-transition.opacity.duration.120ms
                                @click.outside="close()" :style="style" class="fixed z-[9999]">
                                <div x-ref="menu" class="w-44 rounded-xl border bg-white shadow-xl overflow-hidden">
                                    <button type="button"
                                            class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                                            @click.stop.prevent="close(); $dispatch('open-detail', row)">
                                        👁️ Xem chi tiết
                                    </button>
                                    <button type="button"
                                            class="block w-full text-left px-3 py-2 hover:bg-slate-50"
                                            @click="close(); $store.appModal.showFrom('/phieu-dieu-tra/modal/members/' + row.detail_id + '/edit', {title:'Điều chỉnh thành viên'})"
                                            title="Điều chỉnh">✏️ Điều chỉnh</button>
                                    <button type="button"
                                            class="block w-full text-left px-3 py-2 hover:bg-rose-50 text-rose-600"
                                            @click="close(); window.detailUIInstance?.softDelete(row.detail_id)">
                                        ❌ Xóa tạm
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </td>
                <td class="td sticky left-20 z-10 bg-white w-12 tabular-nums" x-text="${data.pagination.from + index}"></td>
            `;
            
            // Tạo các cột dữ liệu động
            const usedKeys = new Set();
            data.groups.forEach(group => {
                group.keys.forEach(key => {
                    if (!usedKeys.has(key)) {
                        usedKeys.add(key);
                        const value = row[key] || '';
                        const displayValue = typeof value === 'boolean' ? (value ? '✓' : '') : value;
                        
                        if (key === 'ho_dem' || key === 'ten') {
                            rowHtml += `
                                <td class="td">
                                    <button type="button" class="text-cyan-700 hover:underline"
                                            @click.stop.prevent="$dispatch('open-detail', row)"
                                            x-text="row.${key} || ''">
                                    </button>
                                </td>
                            `;
                        } else {
                            // Xử lý boolean values
                            if (typeof value === 'boolean') {
                                rowHtml += `<td class="td" x-text="row.${key} ? '✓' : ''"></td>`;
                            } else {
                                rowHtml += `<td class="td" x-text="row.${key} || ''"></td>`;
                            }
                        }
                    }
                });
            });
            
            tr.innerHTML = rowHtml;
            tableBody.appendChild(tr);
            
            // Initialize Alpine.js cho row mới
            if (window.Alpine && Alpine.initTree) {
                Alpine.initTree(tr);
            }
        });

            // Hiển thị pagination
            createPagination(data.pagination);

            resultsContainer.classList.remove('hidden');
            desktopTable.classList.remove('hidden');
            hideLoading();
        } catch (error) {
            console.error('Error in showResults:', error);
            alert('Có lỗi xảy ra khi hiển thị kết quả: ' + error.message);
            hideLoading();
        }
    }
    
    // Gán showResults vào window scope để có thể gọi từ bên ngoài
    window.showResults = showResults;

    function createTableHeaders(groups, columns) {
        try {
            const table = document.getElementById('data-validation-table');
            const thead = table.querySelector('thead');
            
            // Debug: Log groups và columns
            // console.log('Creating headers with groups:', groups);
            // console.log('Creating headers with columns:', columns);
        
            // XÓA HOÀN TOÀN TẤT CẢ HEADER CŨ TRƯỚC KHI TẠO MỚI
            const groupRow = thead.querySelector('tr:first-child');
            const columnRow = thead.querySelector('tr:last-child');
            
            // Lưu lại 3 header đầu (checkbox, menu, #)
            const fixedHeaders = [];
            const groupHeaders = groupRow.querySelectorAll('th');
            const columnHeaders = columnRow.querySelectorAll('th');
            
            // Lưu 3 header đầu
            for (let i = 0; i < 3; i++) {
                if (groupHeaders[i]) fixedHeaders.push(groupHeaders[i].outerHTML);
            }
            
            // console.log(`Found ${groupHeaders.length} existing group headers, removing ${groupHeaders.length - 3} headers`);
            // console.log(`Found ${columnHeaders.length} existing column headers, removing ${columnHeaders.length - 3} headers`);
            
            // Xóa tất cả header cũ
            groupRow.innerHTML = '';
            columnRow.innerHTML = '';
            
            // Thêm lại 3 header đầu chỉ vào group row
            groupRow.innerHTML = fixedHeaders.join('');
            
            const bgMap = ['bg-amber-50', 'bg-sky-50', 'bg-emerald-50', 'bg-rose-50', 'bg-violet-50', 'bg-orange-50', 'bg-slate-50'];
            
            groups.forEach((group, groupIndex) => {
                const bg = bgMap[groupIndex % bgMap.length];
                const colspan = group.keys.length;
                const th = document.createElement('th');
                th.className = `th text-center z-20 ${bg}`;
                th.setAttribute('colspan', colspan);
                th.textContent = group.title;
                groupRow.appendChild(th);
                // console.log(`Group ${groupIndex}: ${group.title} with ${colspan} columns`);
            });
            
            // Không cần thêm lại 3 header đầu vào column row vì group row đã có rowspan="2"
            // Chỉ cần để column row trống để thêm các column headers
            columnRow.innerHTML = '';
            
            // Tạo Set để tránh trùng lặp key
            const usedKeys = new Set();
            const createdHeaders = [];
            
            // Debug: Log tất cả keys từ tất cả groups
            // console.log('All keys from all groups:');
            groups.forEach((group, groupIndex) => {
                // console.log(`Group ${groupIndex} (${group.title}):`, group.keys);
            });
            
            groups.forEach(group => {
                group.keys.forEach(key => {
                    if (!usedKeys.has(key)) {
                        usedKeys.add(key);
                        const th = document.createElement('th');
                        th.className = 'th text-left';
                        th.textContent = columns[key] || key;
                        columnRow.appendChild(th);
                        createdHeaders.push(key);
                    } else {
                        console.warn(`Duplicate key found: ${key}`);
                    }
                });
            });
            
            // console.log('Created headers:', createdHeaders);
        } catch (error) {
            console.error('Error in createTableHeaders:', error);
            alert('Có lỗi xảy ra khi tạo header bảng: ' + error.message);
        }
    }

    function showNoResults() {
        noResultsContainer.classList.remove('hidden');
    }

    function createPagination(pagination) {
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationInfo = document.getElementById('paginationInfo');
        
        if (!paginationContainer || !paginationInfo) return;

        // Hiển thị thông tin pagination
        paginationInfo.innerHTML = `
            Trang ${pagination.current_page} / ${pagination.last_page} 
            (${pagination.from} - ${pagination.to} / ${pagination.total} đối tượng)
        `;

        // Tạo pagination buttons
        let paginationHtml = '<div class="flex items-center gap-2">';
        
        // Previous button
        if (pagination.current_page > 1) {
            paginationHtml += `
                <button type="button" class="px-3 py-1 text-sm border rounded hover:bg-slate-50" 
                        onclick="goToPage(${pagination.current_page - 1})">
                    ← Trước
                </button>
            `;
        }

        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.last_page, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === pagination.current_page;
            paginationHtml += `
                <button type="button" 
                        class="px-3 py-1 text-sm border rounded ${isActive ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-slate-50'}" 
                        onclick="goToPage(${i})">
                    ${i}
                </button>
            `;
        }

        // Next button
        if (pagination.current_page < pagination.last_page) {
            paginationHtml += `
                <button type="button" class="px-3 py-1 text-sm border rounded hover:bg-slate-50" 
                        onclick="goToPage(${pagination.current_page + 1})">
                    Sau →
                </button>
            `;
        }

        paginationHtml += '</div>';
        paginationContainer.innerHTML = paginationHtml;
    }

    // Event listener cho per page select
    const perPageSelect = document.getElementById('perPageSelect');
    if (perPageSelect) {
        perPageSelect.addEventListener('change', function() {
            // Gọi goToPage với trang 1 và per page mới
            goToPage(1);
        });
    }

    // Export button functionality - đã được xóa khỏi HTML
});

// Helper functions for modals - đã được tích hợp vào Alpine.js stores

// Hàm chuyển trang
function goToPage(page) {
    const form = document.getElementById('dataValidationForm');
    if (!form) return;
    
    const formData = new FormData(form);
    const perPageSelect = document.getElementById('perPageSelect');
    const perPage = perPageSelect ? perPageSelect.value : '10';
    
    // Lấy geo data từ form
    const geoData = {
        province_id: formData.get('geo[province_id]'),
        district_id: formData.get('geo[district_id]'),
        ward_id: formData.get('geo[ward_id]'),
        village_id: formData.get('geo[village_id]')
    };
    
    const params = new URLSearchParams({
        academic_year: formData.get('academic_year') || '',
        criteria: formData.get('criteria') || '',
        scheme: formData.get('scheme') || 'old',
        per_page: perPage,
        page: page,
        'geo[province_id]': geoData.province_id || '',
        'geo[district_id]': geoData.district_id || '',
        'geo[ward_id]': geoData.ward_id || '',
        'geo[village_id]': geoData.village_id || ''
    });
    
    // console.log('goToPage params:', params.toString());
    
    // Gọi API với page mới
    fetch(`/kiem-tra-du-lieu/check?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.total > 0) {
                    showResults(data);
                } else {
                    showNoResults();
                }
            } else {
                alert('Có lỗi xảy ra: ' + (data.error || 'Không thể tải dữ liệu'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi tải dữ liệu');
        });
}

// Hàm reload danh sách kiểm tra dữ liệu
function reloadDataValidationResults() {
    // console.log('Reloading data validation results...');
    
    // Lấy dữ liệu từ form hiện tại
    const form = document.getElementById('dataValidationForm');
    if (!form) {
        console.error('Form not found');
        return;
    }
    
    const formData = new FormData(form);
    const perPageSelect = document.getElementById('perPageSelect');
    const perPage = perPageSelect ? perPageSelect.value : '10';
    
    const params = new URLSearchParams();
    
    // Thêm các tham số từ form
    for (let [key, value] of formData.entries()) {
        if (key === 'geo[province_id]' || key === 'geo[district_id]' || key === 'geo[ward_id]' || key === 'geo[village_id]') {
            params.append(key, value);
        } else {
            params.append(key, value);
        }
    }
    
    // Thêm per_page
    params.append('per_page', perPage);
    
    // console.log('Reloading with params:', params.toString());
    
    // Gọi API check với cùng tham số
    fetch(`/kiem-tra-du-lieu/check?${params.toString()}`)
        .then(response => {
            // console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            // console.log('Reload response:', data);
            if (data.success) {
                // Gọi showResults từ window scope
                if (window.showResults) {
                    window.showResults(data);
                } else {
                    // Fallback: trigger lại form submit
                    const form = document.getElementById('dataValidationForm');
                    if (form) {
                        form.dispatchEvent(new Event('submit'));
                    }
                }
            } else {
                console.error('Error reloading data:', data.message);
                alert('Lỗi khi reload dữ liệu: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error reloading data:', error);
            alert('Lỗi kết nối khi reload dữ liệu');
        });
}

// Detail UI Instance để xử lý soft delete
window.detailUIInstance = {
    softDelete(id) {
        // console.log('softDelete called with id:', id);
        
        if (!id || id === 'null') {
            alert('Không tìm thấy ID đối tượng: ' + id);
            return;
        }

        const message = 'Bạn có muốn xóa tạm đối tượng?\nĐể phục hồi đối tượng vào Điều tra -> Danh sách xóa tạm.';
        
        if (confirm(message)) {
            // console.log('User confirmed, sending request to:', `/doituong-dieutra/${id}/soft-delete`);
            
            // Gửi request xóa tạm
            fetch(`/doituong-dieutra/${id}/soft-delete`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ id: id })
            })
            .then(response => {
                // console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                // console.log('Response data:', data);
                if (data.success) {
                    alert('Đã xóa tạm đối tượng thành công');
                    // Reload danh sách thay vì reload trang
                    reloadDataValidationResults();
                } else {
                    alert('Lỗi: ' + (data.message || 'Không thể xóa tạm đối tượng'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Lỗi kết nối: Không thể xóa tạm đối tượng');
            });
        }
    }
};

// Alpine.js functions
function bulkSelect(){
    return {
        selectedCount: 0,
        toggleAll(ev){
            const checked = ev.target.checked;
            document.querySelectorAll('input.row-checkbox').forEach(cb => { cb.checked = checked; });
            this.recount();
        },
        onRowToggle(){ this.recount(); },
        recount(){ this.selectedCount = document.querySelectorAll('input.row-checkbox:checked').length; },
        confirmBulkDelete(){
            if (this.selectedCount === 0) {
                alert('Vui lòng chọn ít nhất một đối tượng để xóa tạm.');
                return;
            }
            
            const message = `Bạn chắc chắn muốn xóa tạm ${this.selectedCount} đối tượng đã chọn?\n\nĐể phục hồi các đối tượng vào Điều tra -> Danh sách xóa tạm.`;
            
            if (confirm(message)) {
                const selectedCheckboxes = document.querySelectorAll('input.row-checkbox:checked');
                const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
                
                // console.log('Submitting bulk soft delete with IDs:', ids);
                
                // Gửi request bulk soft delete
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                ids.forEach(id => {
                    formData.append('ids[]', id);
                });
                
                fetch('{{ route("front.surveys.bulk_soft_delete") }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Đã xóa tạm ${ids.length} đối tượng thành công`);
                        // Reload danh sách thay vì chuyển trang
                        reloadDataValidationResults();
                    } else {
                        alert('Lỗi: ' + (data.message || 'Không thể xóa tạm đối tượng'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Lỗi kết nối: Không thể xóa tạm đối tượng');
                });
            }
        },
        confirmBulkHardDelete(){
            if (this.selectedCount === 0) {
                alert('Vui lòng chọn ít nhất một đối tượng để xóa thật.');
                return;
            }
            
            const message = `⚠️ CẢNH BÁO: Bạn có chắc chắn muốn XÓA THẬT ${this.selectedCount} đối tượng đã chọn?\n\nHành động này sẽ xóa vĩnh viễn các đối tượng khỏi dữ liệu và KHÔNG THỂ PHỤC HỒI!`;
            
            if (confirm(message)) {
                const selectedCheckboxes = document.querySelectorAll('input.row-checkbox:checked');
                const ids = Array.from(selectedCheckboxes).map(cb => cb.value);
                
                // console.log('Submitting bulk hard delete with IDs:', ids);
                
                // Gửi request bulk hard delete
                const formData = new FormData();
                formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
                ids.forEach(id => {
                    formData.append('ids[]', id);
                });
                
                fetch('{{ route("front.surveys.bulk_hard_delete") }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Đã xóa ${ids.length} đối tượng thành công`);
                        // Reload danh sách thay vì chuyển trang
                        reloadDataValidationResults();
                    } else {
                        alert('Lỗi: ' + (data.message || 'Không thể xóa đối tượng'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Lỗi kết nối: Không thể xóa đối tượng');
                });
            }
        }
    }
}

function kebabMenu(){
    return {
        open:false, style:'left:0;top:0', anchor:null,
        openMenu(ev){
            this.anchor = ev.currentTarget;
            this.open = true;
            this.$nextTick(() => this.place());
        },
        close(){ this.open = false; },
        place(){
            if (!this.anchor) return;
            const r = this.anchor.getBoundingClientRect();
            const menu = this.$refs.menu;
            const w = (menu?.offsetWidth  ?? 176);
            const h = (menu?.offsetHeight ?? 160);
            const gap = 8;
            let left = r.right + gap;
            let top  = r.top;
            const vw = window.innerWidth, vh = window.innerHeight;
            if (left + w > vw - 8) left = r.left - w - gap;
            if (top + h > vh - 8)   top  = Math.max(8, vh - h - 8);
            if (top < 8) top = 8;
            this.style = `left:${left}px; top:${top}px`;
        },
        init(){
            this._onScroll = () => { if (this.open) this.place(); };
            this._onResize = () => { if (this.open) this.place(); };
            window.addEventListener('scroll', this._onScroll, true);
            window.addEventListener('resize', this._onResize);
        },
        destroy(){
            window.removeEventListener('scroll', this._onScroll, true);
            window.removeEventListener('resize', this._onResize);
        }
    }
}
</script>
@endpush
