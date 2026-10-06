@extends('layouts.front')
@section('title','Nhập phiếu điều tra (Frontend)')
@section('crumb','Nhập Excel (Frontend)')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div x-data="excelImportUI()" x-init="boot()" class="mx-auto max-w-7xl px-4 py-6">

  <!-- Overlay đọc file -->
  <div x-show="loading" x-cloak class="fixed inset-0 z-[60] grid place-items-center bg-black/30">
    <div class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-xl">
      <svg class="h-6 w-6 animate-spin text-cyan-600" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
      </svg>
      <span class="text-sm text-slate-700">Đang đọc file Excel...</span>
    </div>
  </div>

  <!-- Giới thiệu -->
  <div class="card">
    <h1 class="text-lg font-semibold">Nhập dữ liệu phiếu điều tra (Frontend)</h1>
    <p class="text-sm text-slate-600 mt-1">
      Chọn file Excel, hệ thống sẽ tự ánh xạ cột theo <b>hàng số thứ tự</b> (hàng vàng),
      bạn có thể xem trước và chỉnh lại nếu cần.
    </p>
  </div>

  <!-- Bộ chọn tham số -->
  <div class="mt-3 card grid gap-4 md:grid-cols-12">
    <!-- Năm học -->
    <div class="md:col-span-3" x-cloak>
      <label class="block text-xs text-slate-500 mb-1">Năm học</label>
      <select x-model="academicYear" class="w-full rounded-lg border px-3 py-2" x-ref="academic">
        <template x-for="yy in yearOptions" :key="yy">
          <option :value="yy" :selected="yy===academicYear" x-text="yy"></option>
        </template>
      </select>
    </div>

    

    <!-- File -->
    <div class="md:col-span-6">
      <label class="block text-xs text-slate-500 mb-1">Chọn tệp Excel</label>
      <input type="file" accept=".xlsx,.xls,.csv" @change="onFileChange" class="w-full rounded-lg border px-3 py-2">
      <p class="text-xs text-slate-500 mt-1">Hỗ trợ .xlsx/.xls/.csv. Dung lượng &le; 20MB.</p>
    </div>

    {{-- Geo filter: chiếm trọn hàng --}}
    <div class="md:col-span-12">
      @include('partials.front.geo-filter', [
        // mặc định sơ đồ + chế độ
        'scheme'        => request('scheme','new'),
        'schemeMode'    => request('scheme','new'), // 'old'|'new'
        'showHamlet'    => true,

        // (tùy chọn) trường học:
        'showSchool'    => false,
        'enableSchool'  => request('enable_school', true),
        'schoolName'    => 'school_id',
        'schoolId'      => request('school_id'),
        'schoolWardParam' => 'ward_id',
        'schoolUseCode' => false,

        // layout của partial
        'wrapperClass'  => 'md:col-span-8 grid md:grid-cols-12 gap-3',
        // API lấy danh sách trường
        'schoolDataUrl' => route('front.schools.datapicker'),
      ])
    </div>
  </div>

  <!-- Sheet & mapping -->
  <div class="card" style="display:none">
    <div class="flex items-center gap-3">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Sheet</label>
        <select x-model="selectedSheet" @change="extractSheet()" class="rounded-lg border px-3 py-2" :disabled="!sheetNames.length">
          <template x-for="s in sheetNames" :key="s">
            <option :value="s" x-text="s"></option>
          </template>
        </select>
      </div>
      <div class="text-sm text-slate-500"  >
        <span x-text="fileName || 'Chưa chọn tệp'"></span> —
        <span x-text="`${rowCount} dòng / ${headers.length} cột`"></span>
      </div>
      <div class="ml-auto flex items-center gap-2">
        <button type="button" class="btn-soft" @click="mapFromNumberRow()" :disabled="!headers.length">
          Ánh xạ từ hàng số thứ tự
        </button>
        <button type="button" class="btn-soft" @click="clearMap()" :disabled="!headers.length">Xoá mapping</button>
      </div>
    </div>

  
    <!-- Bảng mapping -->
    <div class="mt-4 grid gap-6" style="display:none">
      <template x-for="(group, gi) in groupedFields()" :key="'g'+gi">
        <div>
          <div class="text-sm font-semibold text-slate-700 mb-2" x-text="group.name"></div>
          <div class="grid gap-3 md:grid-cols-2">
            <template x-for="f in group.items" :key="f.key">
              <div class="grid grid-cols-5 items-center gap-2">
                <div class="col-span-2">
                  <div class="text-sm font-medium" x-text="f.label"></div>
                  <div class="text-xs text-slate-500" x-text="f.key"></div>
                </div>
                <div class="col-span-3">
                  <select class="w-full rounded-lg border px-3 py-2" x-model="mapping[f.key]">
                    <option value="">— Chọn cột —</option>
                    <template x-for="(h,i) in headers" :key="i">
                      <option :value="h" x-text="h"></option>
                    </template>
                  </select>
                </div>
              </div>
            </template>
          </div>
        </div>
      </template>
    </div>
  </div>
  {{-- kết quả --}}
<div class="mt-3 card" x-show="summary.total">
    <div class="flex items-center justify-between">
      <div class="text-sm">
        <b>Kết quả:</b>
        <span x-text="`Tổng ${summary.total} dòng · OK ${summary.ok} · Lỗi ${summary.fail}`"></span>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" class="btn-soft" @click="downloadErrorXLSX()" :disabled="!errorsAll.length">
          Tải danh sách lỗi (.xlsx)
        </button>
      </div>
    </div>

    <template x-if="errorsAll.length">
      <div class="mt-3 overflow-x-auto">
        <table class="min-w-full border text-sm">
          <thead>
            <tr>
              <th class="border px-2">#</th>
           
              <th class="border px-2">STT</th>          <!-- ⬅️ thêm -->
              <th class="border px-2">Số phiếu</th>
              <th class="border px-2">Tên</th>
              <th class="border px-2">Lỗi</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="(e,idx) in errorsAll.slice(0,50)" :key="idx">
              <tr>
                <td class="border px-2" x-text="idx+1"></td>
              
                <td class="border px-2" x-text="e.stt ?? ''"></td>       <!-- ⬅️ thêm -->
                <td class="border px-2" x-text="e.so_phieu ?? ''"></td>
                <td class="border px-2" x-text="e.ten ?? ''"></td>
                <td class="border px-2">
                  <span x-text="Array.isArray(e.messages) ? e.messages.join('; ') : ''"></span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        <p class="mt-2 text-xs text-slate-500" x-show="errorsAll.length>50">
          (Chỉ hiển thị 50 lỗi đầu tiên. Nhấn “Tải danh sách lỗi (.xlsx)” để xem đầy đủ.)
        </p>
      </div>
    </template>
  </div>
  {{-- kết quả --}}
  <!-- Xem nhanh & xuất -->
  <div class="mt-3 card">
    <div class="flex items-center justify-between">
      <h3 class="font-semibold">Xem nhanh 50 dòng đầu (đã áp mapping)</h3>
      <div class="flex items-center gap-2">
        <button type="button" class="btn-soft" @click="downloadJSON()" :disabled="!headers.length">Tải JSON</button>

        <!-- Nút submit theo lô -->
        <button
          type="button"
          id="btnSubmit"
          :class="['btn-primary', !canSubmit() ? 'opacity-50 cursor-not-allowed' : '']"
          @click="submitChunked()"
          :title="!canSubmit() ? firstErrorHint() : ''"
        >
          Lưu vào hệ thống
        </button>
      </div>
    </div>

    <div class="mt-3 overflow-x-auto">
      <table class="min-w-full border">
        <thead>
          <tr>
            <th class="w-12 border px-2">#</th>
            <template x-for="(f,fi) in fields.filter(f => !f.hidden)" :key="fi">
              <th class="border px-2" x-text="f.label"></th>
            </template>
          </tr>
        </thead>
        <tbody>
          <template x-for="(r,ri) in previewMapped()" :key="ri">
            <tr>
              <td class="border px-2" x-text="ri+1"></td>
              <!-- TBODY -->
                <template x-for="(f,fi) in fields.filter(f => !f.hidden)" :key="fi">
                  <td class="border px-2" x-text="formatCell(r[f.key])"></td>
                </template>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- JSON mẫu -->
    <div class="mt-4" style="display:none">
      <h4 class="font-medium">Mẫu 1 dòng (đã ghép meta năm học & địa bàn)</h4>
      <pre class="mt-2 bg-slate-900 text-slate-100 text-xs rounded-lg p-3 overflow-auto"
           x-text="JSON.stringify(applyMeta(previewMapped()[0]||{}), null, 2)"></pre>
    </div>

    <!-- Thiết lập POST API (tùy chọn) -->
    <details class="mt-4" style="display:none">
      <summary class="cursor-pointer text-sm text-slate-600">Thiết lập POST API (tuỳ chọn)</summary>
      <div class="mt-2 grid gap-3 md:grid-cols-12">
        <div class="md:col-span-6">
          <label class="block text-xs text-slate-500 mb-1">Endpoint URL</label>
          <input type="text" x-model="api.url" placeholder="/api/import/surveys"
                 class="w-full rounded-lg border px-3 py-2">
        </div>
        <div class="md:col-span-6">
          <label class="block text-xs text-slate-500 mb-1">Bearer token (nếu có)</label>
          <input type="text" x-model="api.token" class="w-full rounded-lg border px-3 py-2">
        </div>
      </div>
      <p class="text-xs text-slate-500 mt-2">
        Nút “POST lên API” sẽ gửi: <code>[{...row, academic_year, geo:{...}}]</code>
      </p>
    </details>
  </div>

  <!-- Overlay đang gửi (progress) -->
  <div x-show="sending" x-cloak class="fixed inset-0 z-[60] bg-black/40 grid place-items-center">
    <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl">
      <div class="flex items-start gap-3">
        <svg class="h-5 w-5 animate-spin shrink-0" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
        </svg>
        <div class="grow">
          <div class="text-sm font-medium">Đang lưu dữ liệu theo lô…</div>
          <div class="mt-1 text-xs text-slate-600" x-text="progress.message || 'Đang khởi tạo…'"></div>

          <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-200">
            <div class="h-full bg-cyan-600 transition-all" :style="`width:${progress.percent}%`"></div>
          </div>

          <div class="mt-2 flex items-center justify-between text-xs text-slate-600">
            <div>
              <span x-text="`Đã gửi: ${progress.current}/${progress.total} dòng`"></span>
              <span class="mx-1">·</span>
              <span x-text="`OK: ${summary.ok}`"></span>
              <span class="mx-1">·</span>
              <span x-text="`Lỗi: ${summary.fail}`"></span>
            </div>
            <div x-show="progress.batch_total">
              <span x-text="`Lô ${progress.batch_index}/${progress.batch_total}`"></span>
            </div>
          </div>

          <div class="mt-4 flex items-center justify-end gap-2">
            <button type="button" class="btn-soft" @click="abortUpload" :disabled="!abortController">Hủy</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Form fallback (nếu cần submit toàn bộ một lần) -->
  <form x-ref="saveForm" method="POST" action="{{ route('front.surveys.saveimport') }}">
    @csrf
    <input type="hidden" name="academic_year" :value="academicYear">
    <input type="hidden" name="scheme"        x-model="scheme">
    <input type="hidden" name="geo[province_id]" :value="scheme==='old' ? selProvOld : selProvNew">
    <input type="hidden" name="geo[district_id]" :value="scheme==='old' ? selDistOld : ''">
    <input type="hidden" name="geo[ward_id]"     :value="scheme==='old' ? selWardOld : selWardNew">
    <input type="hidden" name="geo[village_id]"   :value="scheme==='old' ? selHamletOld : selHamletNew">
    <textarea name="rows" x-ref="fRows" class="hidden"></textarea>
  </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
  const GEO_BASE = '{{ url('/ajax/geo') }}';
  const GEO_API = {
    provinces: `${GEO_BASE}/provinces`,    // ?scheme=old|new
    children : (id) => `${GEO_BASE}/children/${id}`,
    resolve  : `${GEO_BASE}/resolve`,      // ?scheme=...&code=...
  };
</script>

<script>
/** ====== Cố định các hàng trong file Excel (0-based) ====== **/
const HEADER_ROW_INDEX = 2;   // fallback: Excel row 3
const NUMBER_ROW_INDEX = 3;   // fallback: Excel row 4

/** ====== Utils ====== **/
function makeUniqueHeaders(raw){
  const seen = Object.create(null);
  return raw.map((v, i) => {
    let base = String(v || '').trim();
    if (!base) base = `COL${i+1}`;
    const n = (seen[base] = (seen[base] || 0) + 1);
    return n === 1 ? base : `${base}#${n}`;
  });
}
function getSheetRows(ws){
  const ref = ws['!ref'] || 'A1';
  const R = XLSX.utils.decode_range(ref);
  const W = R.e.c - R.s.c + 1;
  const rows = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, blankrows: false, defval: "" });
  return rows.map(r => {
    if (r.length < W) r = r.concat(Array(W - r.length).fill(""));
    return r.slice(0, W);
  });
}
function detectNumberRowIndex(rows){
  const upto = Math.min(rows.length, 25);
  for (let r = 0; r < upto; r++){
    const row = rows[r] || [];
    let consec = 0;
    for (let c = 0; c < row.length; c++){
      if (String(row[c]).trim() === String(c+1)) consec++;
    }
    const justNums = row.filter(v => /^\s*\d+\s*$/.test(String(v))).length;
    if (consec >= 8 || justNums >= 15) return r;
  }
  return -1;
}
const FIXED_COLMAP = {
  1:'row_no',
  2:'ho_dem', 3:'ten', 4:'dob_day', 5:'dob_month', 6:'dob_year', 7:'is_female',
  8:'ethnicity', 9:'religion', 10:'priority_name',
  11:'chuho_ho_dem', 12:'chuho_ten', 13:'addressline', 14:'so_phieu',
  15:'residence_type_name', 16:'residence_status_name',
  17:'education_block_code', 18:'education_grade_name', 19:'studying_district',
  20:'school_name', 21:'school_code', 22:'graduation_level_name', 23:'is_bo_tuc',
  24:'graduation_year', 25:'vocational_level_name', 26:'vocational_grad_cert_no',
  27:'vocational_grad_year', 28:'hoc_xong_lop', 29:'hoc_xong_nam',
  30:'bo_hoc_lop', 31:'bo_hoc_nam',
  32:'lit_dang_hoc_lop', 33:'lit_hoan_thanh_lop', 34:'lit_tai_mu_muc',
  35:'dis_VAN_DONG', 36:'dis_NGHE_NOI', 37:'dis_NHIN', 38:'dis_THAN_KINH',
  39:'dis_TRI_TUE', 40:'dis_HOC_TAP', 41:'dis_TU_KY', 42:'dis_KHAC',
  43:'dis_has_certificate', 44:'dis_can_learn',
  45:'sc_name', 46:'sc_detail',
  47:'relationship_name', 48:'contact_name', 49:'contact_phone', 50:'contact_note',
};
function buildMapFromNumberRow(headers, numberRow) {
  const map = {};
  for (let i = 0; i < headers.length; i++) {
    const raw = String(numberRow?.[i] ?? '').trim();
    const n = parseInt(raw.replace(/\D+/g,''), 10);
    const key = FIXED_COLMAP[n];
    if (!key) continue;
    map[key] = headers[i];
  }
  return map;
}
function toBool(v){
  const raw = String(v ?? '').trim();
  const s = raw.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
  if (!s) return false;
  if (raw.includes('✓') || raw.includes('✔')) return true;
  if (['1','x','yes','y','true','t','co'].includes(s)) return true;
  if (['0','no','n','false','k','ko','khong'].includes(s)) return false;
  if (/^\d+$/.test(s)) return Number(s) > 0;
  return false;
}

/** ====== Alpine Component ====== **/
window.excelImportUI = function(){
  return {
    // ----- UI state -----
    scheme: 'old', loading: false, sending: false,
    api: { url: '', token: '' },
    excelDataStartRow: 2,                  // Excel row đầu tiên của dữ liệu (1-based)
    errorsAll: [],                         // mảng gom lỗi toàn bộ
    summary: { total: 0, ok: 0, fail: 0 }, // thống kê tổng
    // địa bàn
    provincesOld: [], districtsOld: [], wardsOld: [], hamletsOld: [],
    provincesNew: [], wardsNew: [], hamletsNew: [],

    // selected
    selProvOld: '', selDistOld: '', selWardOld: '', selHamletOld: '',
    selProvNew: '', selWardNew: '', selHamletNew: '',

    getValByName(name){
      const el = document.querySelector(`[name="${name}"]`);
      if (!el) return '';
      if (el.type === 'radio') {
        const checked = document.querySelector(`input[name="${name}"]:checked`);
        return checked ? checked.value : '';
      }
      return el.value ?? '';
    },
    getScheme(){
      return this.getValByName('scheme') || 'new';
    },
    currentGeoFromDOM(){
      const pid = this.getValByName('geo[province_id]');
      const did = this.getValByName('geo[district_id]');
      const wid = this.getValByName('geo[ward_id]');
      const vid = this.getValByName('geo[village_id]');
      return {
        province_id: pid || null,
        district_id: did || null,
        ward_id:     wid || null,
        village_id:  vid || null,
      };
    },
   // --- thay thế 2 dòng cũ ---
    yearOptions: (() => {
      const y = new Date().getFullYear();
      return [`${y-1}-${y}`, `${y}-${y+1}`, `${y+1}-${y+2}`];
    })(),
  academicYear: (() => {
      const y = new Date().getFullYear();
      return `${y-1}-${y}`;       // <-- 2024-2025 khi y=2025
      // Nếu muốn mặc định là 2025-2026 thì dùng: return `${y}-${y+1}`;
    })(),
    // Excel
    wb:null, fileName:'', sheetNames:[], selectedSheet:'',
    headers:[], rows:[], rowCount:0,
    mapping:{},

    // chunk upload state
    chunkSize: 50,
    abortController: null,
    progress: { current:0, total:0, percent:0, ok:0, fail:0, batch_index:0, batch_total:0, message:'' },

    postUrl: '{{ route("front.surveys.saveimport") }}',

    // ------------ helpers ------------
    flashMsg:'', showFlash:false,
    flash(msg){ this.flashMsg = msg; this.showFlash = true; setTimeout(()=> this.showFlash=false, 2500); },

    firstErrorHint(){
      const errs = this.collectErrors();
      return errs.length ? errs[0] : '';
    },
    collectErrors(){
      const errs = [];
      if (!this.headers.length) errs.push('Bạn chưa chọn file Excel.');
      if (!this.academicYear)   errs.push('Vui lòng chọn năm học.');

      const scheme = this.getScheme();
      const geo = this.currentGeoFromDOM();

      if (scheme === 'old') {
        if (!geo.province_id) errs.push('Chưa chọn Tỉnh (cũ).');
        if (!geo.district_id) errs.push('Chưa chọn Quận/Huyện (cũ).');
        if (!geo.ward_id)     errs.push('Chưa chọn Phường/Xã (cũ).');
      } else {
        if (!geo.province_id) errs.push('Chưa chọn Tỉnh (mới).');
        if (!geo.ward_id)     errs.push('Chưa chọn Phường/Xã (mới).');
      }
      return errs;
    },
    validateBeforeSubmit(){
      const errs = this.collectErrors();
      if (errs.length){ this.flash(errs[0]); return false; }
      return true;
    },
    canSubmit(){
      if (!this.headers.length || !this.academicYear) return false;
      const scheme = this.getScheme();
      const geo = this.currentGeoFromDOM();
      return scheme === 'old'
        ? !!(geo.province_id && geo.district_id && geo.ward_id)
        : !!(geo.province_id && geo.ward_id);
    },

    currentGeo(){
      return this.scheme === 'old'
        ? { province_id: this.selProvOld||null, district_id: this.selDistOld||null, ward_id: this.selWardOld||null, village_id: this.selHamletOld||null }
        : { province_id: this.selProvNew||null, district_id: null, ward_id: this.selWardNew||null, village_id: this.selHamletNew||null };
    },

    resetProgress(total){
      this.progress.current = 0;
      this.progress.total = total || 0;
      this.progress.percent = 0;
      this.progress.ok = 0;
      this.progress.fail = 0;
      this.progress.batch_index = 0;
      this.progress.batch_total = 0;
      this.progress.message = '';
    },

    abortUpload(){
        try { this.abortController?.abort(); } catch(_) {}
        this.abortController = null;
        this.resetProgress(0);
        this.sending = false;
        },

    // ------------ lifecycle ------------
    boot(){
      // const y = new Date().getFullYear();
      // this.yearOptions = [ `${y-1}-${y}`, `${y}-${y+1}`, `${y+1}-${y+2}` ];
      // this.academicYear = this.yearOptions[1];

      this.loadProvinces('old');
      this.loadProvinces('new');

      this.$watch('scheme', async (to) => {
        if (to === 'old') {
          this.selProvNew = ''; this.selWardNew = ''; this.wardsNew = []; this.hamletsNew = [];
          if (!this.provincesOld.length) await this.loadProvinces('old');
        } else {
          this.selProvOld = ''; this.selDistOld = ''; this.selWardOld = '';
          this.districtsOld = []; this.wardsOld = []; this.hamletsOld = [];
          if (!this.provincesNew.length) await this.loadProvinces('new');
        }
      });
    },

    // ------------ geo fetchers ------------
    async loadProvinces(scheme){
      try{
        const r = await fetch(`${GEO_API.provinces}?scheme=${scheme}`, { headers:{ 'Accept':'application/json' }, credentials:'same-origin' });
        const items = await r.json();
        if (scheme === 'old') this.provincesOld = items;
        else this.provincesNew = items;
      }catch(e){ console.error('loadProvinces', e); }
    },
    async fetchChildren(parentId){
      if(!parentId) return [];
      try{
        const r = await fetch(GEO_API.children(parentId), { headers:{ 'Accept':'application/json' }, credentials:'same-origin' });
        const j = await r.json();
        return Array.isArray(j.children) ? j.children : [];
      }catch(e){ console.error('fetchChildren', e); return []; }
    },
    async onChangeProvOld(e){
      this.selProvOld = e.target.value;
      this.selDistOld = ''; this.selWardOld = ''; this.selHamletOld = '';
      this.districtsOld = await this.fetchChildren(this.selProvOld);
      this.wardsOld = []; this.hamletsOld = [];
    },
    async onChangeDistOld(e){
      this.selDistOld = e.target.value;
      this.selWardOld = ''; this.selHamletOld = '';
      this.wardsOld = await this.fetchChildren(this.selDistOld);
      this.hamletsOld = [];
    },
    async onChangeWardOld(e){
      this.selWardOld = e.target.value;
      this.selHamletOld = '';
      this.hamletsOld = await this.fetchChildren(this.selWardOld);
    },
    onChangeHamletOld(e){ this.selHamletOld = e.target.value; },

    async onChangeProvNew(e){
      this.selProvNew = e.target.value;
      this.selWardNew = ''; this.selHamletNew = '';
      this.wardsNew = await this.fetchChildren(this.selProvNew);
      this.hamletsNew = [];
    },
    async onChangeWardNew(e){
      this.selWardNew = e.target.value;
      this.selHamletNew = '';
      this.hamletsNew = await this.fetchChildren(this.selWardNew);
    },
    onChangeHamletNew(e){ this.selHamletNew = e.target.value; },

    // ------------ file handling ------------
    onFileChange(e){
      const file = e.target.files?.[0]; if(!file) return;
      this.fileName = file.name;
      this.loading = true;

      const reader = new FileReader();
      reader.onload = ev => {
        this.$nextTick(() => {
          setTimeout(() => {
            try {
              const data = new Uint8Array(ev.target.result);
              this.wb = XLSX.read(data, { type:'array' });
              this.sheetNames = this.wb.SheetNames || [];
              this.selectedSheet = this.sheetNames[0] || '';
              this.extractSheet();
            } catch (err) {
              console.error(err);
              alert('Không đọc được tệp Excel. Vui lòng kiểm tra lại.');
            } finally {
              this.loading = false;
            }
          }, 10);
        });
      };
      reader.onerror = () => { this.loading = false; alert('Lỗi khi đọc file. Vui lòng thử lại.'); };
      reader.readAsArrayBuffer(file);
    },

    extractSheet(){
      if(!this.wb || !this.selectedSheet) return;
      
      const ws = this.wb.Sheets[this.selectedSheet];
      const rows2D = getSheetRows(ws);

      let numberIdx = detectNumberRowIndex(rows2D);
      let headerIdx = numberIdx > 0 ? numberIdx - 1 : HEADER_ROW_INDEX;
      if (numberIdx < 0) { numberIdx = NUMBER_ROW_INDEX; headerIdx = HEADER_ROW_INDEX; }

      const headersRaw = (rows2D[headerIdx] || []).map(h => String(h||'').trim());
      this.headers = makeUniqueHeaders(headersRaw);
      const numberRow = rows2D[numberIdx] || [];
      this.mapping = buildMapFromNumberRow(this.headers, numberRow);

      const dataRows = rows2D.slice(numberIdx + 1);
      this.rows = dataRows.map(arr => {
        const o = {};
        this.headers.forEach((h,i)=> o[h] = arr[i]);
        return o;
      });
      this.rowCount = this.rows.length;

      this._lastNumberRowIndex = numberIdx;
      this.excelDataStartRow = numberIdx + 2; 
      console.log('Header ở hàng:', headerIdx+1, ' | Số thứ tự ở hàng:', numberIdx+1);
      console.log('Mapping tự động:', this.mapping);
    },
    mapFromNumberRow(){
      if (!this.headers.length || !this.wb) return;
      const ws = this.wb.Sheets[this.selectedSheet];
      const rows2D = getSheetRows(ws);
      let numberIdx = detectNumberRowIndex(rows2D);
      if (numberIdx < 0) numberIdx = (this._lastNumberRowIndex ?? NUMBER_ROW_INDEX);
      const numberRow = rows2D[numberIdx] || [];
      this.mapping = buildMapFromNumberRow(this.headers, numberRow);
    },
    clearMap(){ this.mapping = {}; },

    // ------------ grouping & mapping ------------
    fields: [
      { key:'row_no', label:'STT', group:'Thông tin đối tượng', hidden:true }, // ⬅️ thêm dòng này lên đầu
      { key:'ho_dem',label:'Họ đệm',group:'Thông tin đối tượng' },
      { key:'ten',label:'Tên',group:'Thông tin đối tượng',required:true },
      { key:'dob_day',label:'Ngày sinh',group:'Thông tin đối tượng' },
      { key:'dob_month',label:'Tháng sinh',group:'Thông tin đối tượng' },
      { key:'dob_year',label:'Năm sinh',group:'Thông tin đối tượng' },
      { key:'is_female',label:'Giới tính (Nữ)',group:'Thông tin đối tượng' },
      { key:'ethnicity',label:'Dân tộc',group:'Thông tin đối tượng' },
      { key:'religion',label:'Tôn giáo',group:'Thông tin đối tượng' },
      { key:'priority_name',label:'Diện ưu tiên',group:'Thông tin đối tượng' },
      { key:'chuho_ho_dem',label:'Chủ hộ - Họ đệm',group:'Thông tin nơi đang ở' },
      { key:'chuho_ten',label:'Chủ hộ - Tên',group:'Thông tin nơi đang ở' },
      { key:'addressline',label:'Địa chỉ',group:'Thông tin nơi đang ở' },
      { key:'so_phieu',label:'Số phiếu',group:'Thông tin nơi đang ở',required:true },
      { key:'residence_type_name',label:'Diện cư trú',group:'Thông tin nơi đang ở' },
      { key:'residence_status_name',label:'Tình trạng cư trú',group:'Thông tin nơi đang ở' },
      { key:'education_block_code',label:'Khối học',group:'Thông tin học tập' },
      { key:'education_grade_name',label:'Lớp học',group:'Thông tin học tập' },
      { key:'studying_district',label:'Quận/Huyện học',group:'Thông tin học tập' },
      { key:'school_name',label:'Tên trường',group:'Thông tin học tập' },
      { key:'school_code',label:'Mã trường',group:'Thông tin học tập' },
      { key:'graduation_level_name',label:'Bậc TN PT',group:'Thông tin học tập' },
      { key:'is_bo_tuc',label:'Bổ túc',group:'Thông tin học tập' },
      { key:'graduation_year',label:'Năm TN PT',group:'Thông tin học tập' },
      { key:'vocational_level_name',label:'Bậc TN nghề',group:'Thông tin học tập' },
      { key:'vocational_grad_cert_no',label:'Số bằng TN',group:'Thông tin học tập' },
      { key:'vocational_grad_year',label:'Năm TN nghề',group:'Thông tin học tập' },
      { key:'hoc_xong_lop',label:'Học xong lớp',group:'Thông tin học tập' },
      { key:'hoc_xong_nam',label:'Học xong năm',group:'Thông tin học tập' },
      { key:'bo_hoc_lop',label:'Bỏ học lớp',group:'Thông tin học tập' },
      { key:'bo_hoc_nam',label:'Bỏ học năm',group:'Thông tin học tập' },
      { key:'lit_dang_hoc_lop',label:'Đang học lớp',group:'Xóa mù chữ' },
      { key:'lit_hoan_thanh_lop',label:'Hoàn thành lớp',group:'Xóa mù chữ' },
      { key:'lit_tai_mu_muc',label:'Tái mù mức',group:'Xóa mù chữ' },
      { key:'dis_VAN_DONG',label:'KT vận động',group:'Khuyết tật' },
      { key:'dis_NGHE_NOI',label:'KT nghe, nói',group:'Khuyết tật' },
      { key:'dis_NHIN',label:'KT nhìn',group:'Khuyết tật' },
      { key:'dis_THAN_KINH',label:'KT thần kinh',group:'Khuyết tật' },
      { key:'dis_TRI_TUE',label:'KT trí tuệ',group:'Khuyết tật' },
      { key:'dis_HOC_TAP',label:'KT học tập',group:'Khuyết tật' },
      { key:'dis_TU_KY',label:'Tự kỷ',group:'Khuyết tật' },
      { key:'dis_KHAC',label:'KT khác',group:'Khuyết tật' },
      { key:'dis_has_certificate',label:'Có chứng nhận',group:'Khuyết tật' },
      { key:'dis_can_learn',label:'Khả năng học',group:'Khuyết tật' },
      { key:'sc_name',label:'Hoàn cảnh ĐB',group:'Hoàn cảnh' },
      { key:'sc_detail',label:'Chi tiết HC',group:'Hoàn cảnh' },
      { key:'relationship_name',label:'Quan hệ với chủ hộ',group:'Thông tin liên lạc' },
      { key:'contact_name',label:'Họ tên (Cha/Mẹ)',group:'Thông tin liên lạc' },
      { key:'contact_phone',label:'Điện thoại',group:'Thông tin liên lạc' },
      { key:'contact_note',label:'Ghi chú',group:'Thông tin liên lạc' },
    ],
    groupedFields(){
      const groups = {};
      for (const f of this.fields){
        if (!groups[f.group]) groups[f.group] = [];
        groups[f.group].push(f);
      }
      return Object.keys(groups).map(name => ({ name, items: groups[name] }));
    },
    mapOne(row){
      const r = {};
      for (const f of this.fields) {
        const col = this.mapping[f.key];
        r[f.key] = col ? row[col] : '';
      }
        // Lấy STT (cột số thứ tự đầu tiên) nếu có mapping
      const colRowNo = this.mapping['row_no'];
      if (colRowNo) {
        const n = parseInt(row[colRowNo], 10);
        r.row_no = isNaN(n) ? '' : n;
      }
      
      const boolKeys = ['is_female','is_bo_tuc', ...this.fields.filter(f => f.key.startsWith('dis_')).map(f => f.key)];
      boolKeys.forEach(k => r[k] = toBool(r[k]));
      ['dob_day','dob_month','dob_year','hoc_xong_lop','bo_hoc_lop'].forEach(k=>{
        const v = parseInt(r[k],10); r[k] = isNaN(v) ? '' : v;
      });
      if (r.row_no !== undefined){
        const n = parseInt(r.row_no,10); r.row_no = isNaN(n) ? '' : n;
      }
      return r;
    },
    mapAll(){ return this.rows.map(r => this.mapOne(r)); },
    previewMapped(){ return this.mapAll().slice(0,50); },
    applyMeta(row){
      const scheme = this.getScheme();
      const geo = this.currentGeoFromDOM();
      return { ...row, academic_year: this.academicYear, scheme, geo };
    },

    // ------------ submit theo lô 50 dòng ------------
    async submitChunked(){
      if (!this.validateBeforeSubmit()) return;
      const scheme = this.getScheme();
      const geo    = this.currentGeoFromDOM();
      const allRows = this.mapAll(); // rows thuần (không applyMeta)
      const total   = allRows.length;
      if (!total) { alert('Không có dữ liệu để gửi.'); return; }

      // Chia lô
      const size = this.chunkSize;
      const chunks = [];
      for (let i = 0; i < total; i += size) chunks.push(allRows.slice(i, i + size));

      // Reset thống kê & lỗi
      this.errorsAll = [];
      this.summary   = { total, ok: 0, fail: 0 };

      this.resetProgress(total);
      this.progress.batch_total = chunks.length;
      this.sending = true;

      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      this.abortController = new AbortController();

      try {
        for (let i = 0; i < chunks.length; i++) {
          if (this.abortController?.signal?.aborted) { this.progress.message = 'Đã hủy bởi người dùng.'; break; }

          this.progress.batch_index = i + 1;
          const chunk = chunks[i];
          const chunkOffset = i * size;

          const body = {
            chunked: true,
            batch_index: i + 1,
            batch_total: chunks.length,
            overall_total: total,
            chunk_size: chunk.length,
            chunk_offset: chunkOffset,
            file_name: this.fileName || null,
            sheet_name: this.selectedSheet || null,
            excel_data_start_row: this.excelDataStartRow,

            academic_year: this.academicYear,
            scheme: scheme,
            geo: geo,
            rows: chunk
          };

          this.progress.message = `Đang gửi lô ${i + 1}/${chunks.length}…`;

          const res = await fetch(this.postUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': token
            },
            body: JSON.stringify(body),
            signal: this.abortController.signal
          });

          let j = null;
          try { j = await res.clone().json(); } catch(_) {}

          // Cập nhật progress bar
          this.progress.current = Math.min(total, this.progress.current + chunk.length);
          this.progress.percent = Math.round(this.progress.current * 100 / total);

          // Tính OK/Fail cho lô
          const okCount   = Number(j?.ok_count   ?? (res.ok ? chunk.length : 0));
          const failCount = Number(j?.fail_count ?? (res.ok ? 0 : chunk.length));
          this.summary.ok   += okCount;
          this.summary.fail += failCount;

          // Gom lỗi từng dòng (server nên trả row_errors[] với index hoặc excel_row)
          if (Array.isArray(j?.row_errors)) {
            let list = j.row_errors;

            // Dạng server hiện tại: row_errors = [index,...], message_errors = ["msg",...]
            if (list.length && typeof list[0] === 'number' && Array.isArray(j.message_errors)) {
              list = list.map((idx, k) => ({
                index: idx,
                messages: [ j.message_errors[k] ?? 'Lỗi không xác định' ],
                // so_phieu/ten nếu server có thể bổ sung thì càng tốt
              }));
            }
 
            const normalized = this.normalizeRowErrors(list, chunk, j?.message_errors || []);
            this.errorsAll.push(...normalized);
          } else if (res.status === 422) {
            // Dự phòng: lỗi validate chung của request (không phải lỗi từng dòng)
            const text = j?.message || 'Dữ liệu không hợp lệ (422).';
            this.errorsAll.push({ excel_row: null, so_phieu: null, ten: null, messages: [text], index: null });
          }

          const msg = j?.message || (res.ok
            ? `Đã gửi xong lô ${i + 1}/${chunks.length}.`
            : `Lỗi lô ${i + 1}/${chunks.length}.`);
          this.progress.message = msg;
        }
      } catch (err) {
        this.progress.message = (err?.name === 'AbortError') ? 'Đã hủy bởi người dùng.' : `Lỗi: ${err?.message || err}`;
      } finally {
        this.abortController = null;
        // Nếu muốn tự ẩn overlay khi thành công hoàn toàn (không lỗi):
        if (this.summary.fail === 0) setTimeout(() => { this.sending = false; }, 800);
        else setTimeout(() => { this.sending = false; }, 300); // ẩn sớm, vì đã có bảng lỗi ở dưới
      }
    },

// cập nhật chữ ký + logic:
normalizeRowErrors(rowErrors, chunk, messageErrors = []){
  return (rowErrors || []).map((e, k) => {
    // 1) Lấy STT
    let stt = null, msgs = [];

    if (typeof e === 'number' || typeof e === 'string') {
      stt = Number(e);
    } else if (e && typeof e === 'object') {
      stt = Number(e.stt ?? e.row_no ?? e.index ?? NaN);
      if (Array.isArray(e.messages)) msgs = e.messages;
      else if (e.message) msgs = [String(e.message)];
    }

    // 2) Ghép với message_errors[k] nếu chưa có message
    if (!msgs.length && Array.isArray(messageErrors)) {
      const m = messageErrors[k];
      if (m !== undefined && m !== null) msgs = [String(m)];
    }
    if (!msgs.length) msgs = ['Lỗi không xác định'];

    // 3) Tìm lại record trong chunk theo STT (row_no) để lấy so_phieu/ten
    const rowMatch = Array.isArray(chunk)
      ? chunk.find(r => Number(r?.row_no) === stt)
      : null;

    return {
      stt: stt,
      so_phieu: (rowMatch?.so_phieu ?? (e?.so_phieu ?? '')),
      ten:      (rowMatch?.ten      ?? (e?.ten      ?? '')),
      messages: msgs,
      index: null, // không dùng index nữa
    };
  });
},


downloadErrorXLSX(){
  if (!this.errorsAll.length) { alert('Không có lỗi.'); return; }
  const rows = this.errorsAll.map(e => ({
    'Dòng Excel': e.excel_row ?? '',
    'Số phiếu'  : e.so_phieu ?? '',
    'Tên'       : e.ten ?? '',
    'Lỗi'       : Array.isArray(e.messages) ? e.messages.join('; ') : ''
  }));
  const ws = XLSX.utils.json_to_sheet(rows);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Errors');
  XLSX.writeFile(wb, `import_errors_${Date.now()}.xlsx`);
},

    // ------------ tiện ích khác ------------
    downloadJSON(){
      const data = this.mapAll().map(r => this.applyMeta(r));
      const blob = new Blob([JSON.stringify(data,null,2)], {type:'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = `import_${Date.now()}.json`; a.click();
      URL.revokeObjectURL(url);
    },
    formatCell(v){ if (v===true) return '✓'; if (v===false) return ''; return String(v ?? ''); },
  }
}
</script>
@endpush
