@extends('layouts.front')
@section('title','Nhập phiếu điều tra (Frontend)')
@section('crumb','Nhập Excel (Frontend)')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div x-data="excelImportUI()" x-init="boot()" class="mx-auto max-w-7xl px-4 py-6">

    <div x-show="loading" x-cloak
        class="fixed inset-0 z-[60] grid place-items-center bg-black/30">
    <div class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-xl">
        <svg class="h-6 w-6 animate-spin text-cyan-600" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10"
                stroke="currentColor" stroke-width="4" fill="none"></circle>
        <path class="opacity-75" fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
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
  <div class="card grid gap-4 md:grid-cols-12">
    <!-- Năm học -->
    <div class="md:col-span-3">
      <label class="block text-xs text-slate-500 mb-1">Năm học</label>
      <select x-model="academicYear" class="w-full rounded-lg border px-3 py-2">
        <template x-for="yy in yearOptions" :key="yy">
          <option :value="yy" x-text="yy"></option>
        </template>
      </select>
    </div>

    <!-- Sơ đồ địa bàn -->
    <div class="md:col-span-3">
      <label class="block text-xs text-slate-500 mb-1">Sơ đồ địa bàn</label>
      <div class="flex items-center gap-4">
        <label class="inline-flex items-center gap-2">
          <input type="radio" value="old" x-model="scheme" class="accent-cyan-600"> Cũ (Tỉnh → Huyện → Xã)
        </label>
        <label class="inline-flex items-center gap-2">
          <input type="radio" value="new" x-model="scheme" class="accent-cyan-600"> Mới (Tỉnh → Xã)
        </label>
      </div>
    </div>

    <!-- File -->
    <div class="md:col-span-6">
      <label class="block text-xs text-slate-500 mb-1">Chọn tệp Excel</label>
      <input type="file" accept=".xlsx,.xls,.csv" @change="onFileChange" class="w-full rounded-lg border px-3 py-2">
      <p class="text-xs text-slate-500 mt-1">Hỗ trợ .xlsx/.xls/.csv. Dung lượng &lt;= 20MB.</p>
    </div>

    <!-- Địa bàn CŨ -->
    <template x-if="scheme==='old'">
        <div class="md:col-span-12 grid gap-3 md:grid-cols-12">
            <div class="md:col-span-4">
            <label class="block text-xs text-slate-500 mb-1">Tỉnh (cũ)</label>
                <select x-model="selProvOld" class="w-full rounded-lg border px-3 py-2"
                        @change="onChangeProvOld" x-ref="provOld">
                <option value="">— Chọn tỉnh —</option>
                <template x-for="p in provincesOld" :key="'op'+p.id">
                    <option :value="p.id" x-text="p.name"></option>
                </template>
                </select>
            </div>
            <div class="md:col-span-4">
            <label class="block text-xs text-slate-500 mb-1">Quận/Huyện (cũ)</label>
                <select x-model="selDistOld" class="w-full rounded-lg border px-3 py-2"
                        @change="onChangeDistOld" x-ref="distOld" :disabled="!districtsOld.length">
                <option value="">— Chọn quận/huyện —</option>
                <template x-for="d in districtsOld" :key="'od'+d.id">
                    <option :value="d.id" x-text="d.name"></option>
                </template>
                </select>
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs text-slate-500 mb-1">Phường/Xã (cũ)</label>
                <select x-model="selWardOld" class="w-full rounded-lg border px-3 py-2"
                            @change="onChangeWardOld" x-ref="wardOld" :disabled="!wardsOld.length">
                    <option value="">— Chọn phường/xã —</option>
                    <template x-for="w in wardsOld" :key="'ow'+w.id">
                        <option :value="w.id" x-text="w.name"></option>
                    </template>
                </select>
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (cũ)</label>
                <select class="w-full rounded-lg border px-3 py-2"
                        x-model="selHamletOld"
                        @change="onChangeHamletOld"
                        :disabled="!hamletsOld.length">
                    <option value="">— Chọn thôn/ấp —</option>
                    <template x-for="h in hamletsOld" :key="'oh'+h.id">
                    <option :value="h.id" x-text="h.name"></option>
                    </template>
                </select>
            </div>
        </div>
    </template>

    <!-- Địa bàn MỚI -->
    <template x-if="scheme==='new'">
        <div class="md:col-span-12 grid gap-3 md:grid-cols-12">
            <div class="md:col-span-6">
                <label class="block text-xs text-slate-500 mb-1">Tỉnh (mới)</label>
                <select x-model="selProvNew" class="w-full rounded-lg border px-3 py-2"
                            @change="onChangeProvNew" x-ref="provNew">
                    <option value="">— Chọn tỉnh —</option>
                    <template x-for="p in provincesNew" :key="'np'+p.id">
                        <option :value="p.id" x-text="p.name"></option>
                    </template>
                </select>
            </div>
            <div class="md:col-span-6">
                <label class="block text-xs text-slate-500 mb-1">Phường/Xã (mới)</label>
                <select x-model="selWardNew" class="w-full rounded-lg border px-3 py-2"
                        @change="onChangeWardNew" x-ref="wardNew" :disabled="!wardsNew.length">
                <option value="">— Chọn phường/xã —</option>
                <template x-for="w in wardsNew" :key="'nw'+w.id">
                    <option :value="w.id" x-text="w.name"></option>
                </template>
                </select>
            </div>
            <div class="md:col-span-6">
                <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (mới)</label>
                <select class="w-full rounded-lg border px-3 py-2"
                        x-model="selHamletNew"
                        @change="onChangeHamletNew"
                        :disabled="!hamletsNew.length">
                    <option value="">— Chọn thôn/ấp —</option>
                    <template x-for="h in hamletsNew" :key="'nh'+h.id">
                    <option :value="h.id" x-text="h.name"></option>
                    </template>
                </select>
            </div>
        </div>
    </template>
  </div>

  <!-- Sheet & mapping -->
  <div class="card">
    <div class="flex items-center gap-3">
      <div>
        <label class="block text-xs text-slate-500 mb-1">Sheet</label>
        <select x-model="selectedSheet" @change="extractSheet()" class="rounded-lg border px-3 py-2" :disabled="!sheetNames.length">
          <template x-for="s in sheetNames" :key="s">
            <option :value="s" x-text="s"></option>
          </template>
        </select>
      </div>
      <div class="text-sm text-slate-500">
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
    <div class="mt-4 grid gap-6">
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

  <!-- Xem nhanh & xuất -->
  <div class="card">
    <div class="flex items-center justify-between">
      <h3 class="font-semibold">Xem nhanh 50 dòng đầu (đã áp mapping)</h3>
      <div class="flex items-center gap-2">
        <button type="button" class="btn-soft" @click="downloadJSON()" :disabled="!headers.length">Tải JSON</button>
        {{-- <button type="button" class="btn-primary" @click="submitAll()" :disabled="!canSubmit()">Lưu vào hệ thống</button> --}}
        <button
            type="button"
            id="btnSubmit"
            :class="['btn-primary', !canSubmit() ? 'opacity-50 cursor-not-allowed' : '']"
            @click="onSubmitClick"
            :title="!canSubmit() ? firstErrorHint() : ''"
          >
            Lưu vào hệ thống
          </button>
        {{-- <button type="button" class="btn-primary"
            @click="submitForm()"
            :disabled="!canSubmit()">Lưu vào hệ thống</button> --}}
        </div>
    </div>
    <div class="mt-3 overflow-x-auto">
      <table class="min-w-full border">
        <thead>
          <tr>
            <th class="w-12 border px-2">#</th>
            <template x-for="(f,fi) in fields" :key="fi">
              <th class="border px-2" x-text="f.label"></th>
            </template>
          </tr>
        </thead>
        <tbody>
          <template x-for="(r,ri) in previewMapped()" :key="ri">
            <tr>
              <td class="border px-2" x-text="ri+1"></td>
              <template x-for="(f,fi) in fields" :key="fi">
                <td class="border px-2" x-text="formatCell(r[f.key])"></td>
              </template>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- JSON mẫu -->
    <div class="mt-4">
      <h4 class="font-medium">Mẫu 1 dòng (đã ghép meta năm học & địa bàn)</h4>
      <pre class="mt-2 bg-slate-900 text-slate-100 text-xs rounded-lg p-3 overflow-auto"
           x-text="JSON.stringify(applyMeta(previewMapped()[0]||{}), null, 2)"></pre>
    </div>

    <!-- Thiết lập POST API -->
    <details class="mt-4">
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
  <!-- Overlay đang gửi -->
    <div x-show="sending" x-cloak class="fixed inset-0 z-[60] bg-black/40 grid place-items-center">
        <div class="rounded-xl bg-white p-4 shadow-lg">
            <div class="flex items-center gap-3">
            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
            </svg>
            <span class="text-sm">Đang lưu dữ liệu, vui lòng đợi…</span>
            </div>
        </div>
    </div>
    <form x-ref="saveForm" method="POST" action="{{ route('front.surveys.saveimport') }}">
        @csrf
        <input type="hidden" name="academic_year" :value="academicYear">
        <input type="hidden" name="scheme"        x-model="scheme">

        <!-- geo: tự đổi theo scheme -->
        <input type="hidden" name="geo[province_id]" :value="scheme==='old' ? selProvOld : selProvNew">
        <input type="hidden" name="geo[district_id]" :value="scheme==='old' ? selDistOld : ''">
        <input type="hidden" name="geo[ward_id]"     :value="scheme==='old' ? selWardOld : selWardNew">
        <input type="hidden" name="geo[hamlet_id]"
            :value="scheme==='old' ? selHamletOld : selHamletNew">
        <textarea name="rows" x-ref="fRows" class="hidden"></textarea>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
  // Base cho nhóm route mới
  const GEO_BASE = '{{ url('/ajax/geo') }}';

  const GEO_API = {
    provinces: `${GEO_BASE}/provinces`,           // ?scheme=old|new
    children : (id) => `${GEO_BASE}/children/${id}`,
    resolve  : `${GEO_BASE}/resolve`,             // ?scheme=...&code=...
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
// Đọc sheet -> mảng 2D có bề rộng chuẩn (giữ ô trống)
function getSheetRows(ws){
  const ref = ws['!ref'] || 'A1';
  const R = XLSX.utils.decode_range(ref);
  const W = R.e.c - R.s.c + 1;
  const rows = XLSX.utils.sheet_to_json(ws, {
    header: 1, raw: false, blankrows: false, defval: ""
  });
  return rows.map(r => {
    if (r.length < W) r = r.concat(Array(W - r.length).fill(""));
    return r.slice(0, W);
  });
}
// Tự động dò hàng có dãy 1..n
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
  const s = raw.toLowerCase()
               .normalize('NFD').replace(/[\u0300-\u036f]/g,''); // bỏ dấu: "có" -> "co"

  if (!s) return false;
  if (raw.includes('✓') || raw.includes('✔')) return true;

  if (['1','x','yes','y','true','t','co'].includes(s)) return true;
  if (['0','no','n','false','k','ko','khong'].includes(s)) return false;

  // ô toàn số khác 0 coi là true
  if (/^\d+$/.test(s)) return Number(s) > 0;

  return false;
}
/** ====== Alpine Component ====== **/
window.excelImportUI = function(){
  return {
    // ----- UI state -----
    scheme: 'old', loading: false, sending: false,  
    api: { url: '', token: '' },

    // dữ liệu địa bàn (dummy để tránh lỗi ReferenceError; bạn có thể thay bằng gọi API thật)
    provincesOld: [], districtsOld: [], wardsOld: [], hamletsOld: [],
    provincesNew: [],  wardsNew: [], hamletsNew: [],

    // lựa chọn địa bàn
    selProvOld: '', selDistOld: '', selWardOld: '', selHamletOld: '',
    selHamletNew: '', selProvNew: '', selWardNew: '',

    academicYear:'', yearOptions:[],

    // Excel
    wb:null, fileName:'', sheetNames:[], selectedSheet:'',
    headers:[], rows:[], rowCount:0,
    mapping:{},
    showFlash:false, flashMsg:'', triedSubmit:false,

    flash(msg){ this.flashMsg = msg; this.showFlash = true; setTimeout(()=> this.showFlash=false, 2500); },

    firstErrorHint(){
      const errs = this.collectErrors();
      return errs.length ? errs[0] : '';
    },

    collectErrors(){
      const errs = [];
      if (!this.headers.length) errs.push('Bạn chưa chọn file Excel.');
      if (!this.academicYear)   errs.push('Vui lòng chọn năm học.');

      if (this.scheme === 'old') {
        if (!this.selProvOld) errs.push('Chưa chọn Tỉnh (cũ).');
        if (!this.selDistOld) errs.push('Chưa chọn Quận/Huyện (cũ).');
        if (!this.selWardOld) errs.push('Chưa chọn Phường/Xã (cũ).');
        // THÔN/ẤP để trống được -> không check nếu bạn muốn cho phép null
      } else {
        if (!this.selProvNew) errs.push('Chưa chọn Tỉnh (mới).');
        if (!this.selWardNew) errs.push('Chưa chọn Phường/Xã (mới).');
        // THÔN/ẤP (mới) để trống được
      }
      return errs;
    },

    validateBeforeSubmit(){
      const errs = this.collectErrors();
      if (errs.length){
        this.flash(errs[0]);            // hoặc: alert(errs.join('\n'))
        this.triedSubmit = true;
        return false;
      }
      return true;
    },

    onSubmitClick(){
      if (!this.validateBeforeSubmit()) return;
      // đủ điều kiện => submit
      this.submitForm();
    },

    // định nghĩa các field để render mapping và preview
    fields: [
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

    postUrl: '{{ route("front.surveys.saveimport") }}', // route Laravel xử lý lưu

    // ===== Các hàm phụ trợ mới =====
  
    submitForm({debug=false} = {}){
        if (!this.validateBeforeSubmit()) return;

        let rows = this.mapAll();
        if (debug) rows = this.previewMapped(); // gửi ít dòng khi debug

        this.$refs.fRows.value = JSON.stringify(rows);
        this.$refs.saveForm.submit();
    },
    canSubmit(){
      if (!this.headers.length || !this.academicYear) return false;
      return this.scheme === 'old'
          ? !!(this.selProvOld && this.selDistOld && this.selWardOld)   // ❌ không cần this.selHamletOld
          : !!(this.selProvNew && this.selWardNew);                    // ❌ không cần this.selHamletNew
    }, 
    validateBeforeSubmit(){
        const errs = [];
        if (!this.headers.length) errs.push('Bạn chưa chọn file Excel.');
        if (!this.academicYear) errs.push('Vui lòng chọn năm học.');
        if (this.scheme === 'old') {
            if (!this.selProvOld) errs.push('Chưa chọn Tỉnh (cũ).');
            if (!this.selDistOld) errs.push('Chưa chọn Quận/Huyện (cũ).');
            if (!this.selWardOld) errs.push('Chưa chọn Phường/Xã (cũ).');
            // if (!this.selHamletOld) errs.push('Chưa chọn Thôn/Ấp (cũ).');
        } else {
            if (!this.selProvNew) errs.push('Chưa chọn Tỉnh (mới).');
            if (!this.selWardNew) errs.push('Chưa chọn Phường/Xã (mới).');
            // if (!this.selHamletNew) errs.push('Chưa chọn Thôn/Ấp (mới).');
        }
        if (errs.length){ alert(errs.join('\n')); return false; }
        return true;
    },

    mapAll(){               // lấy TẤT CẢ dòng đã mapping (không chỉ 50 dòng preview)
        return this.rows.map(r => this.mapOne(r));
    },
    
    buildPayload(){
        const geo = this.scheme === 'old'
            ? { province_id: this.selProvOld||null, district_id: this.selDistOld||null,
                ward_id: this.selWardOld||null, hamlet_id: this.selHamletOld||null }
            : { province_id: this.selProvNew||null, district_id: null,
                ward_id: this.selWardNew||null, hamlet_id: this.selHamletNew||null };

        return {
            academic_year: this.academicYear,
            scheme: this.scheme,
            geo,
            total_rows: this.rows.length,
            rows: this.mapAll(),
        };
    },
    async submitAll(){
        if (!this.validateBeforeSubmit()) return;

        this.sending = true;
        try{
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch(this.postUrl, {
            method: 'POST',
            headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token ?? ''
            },
            body: JSON.stringify(this.buildPayload()),
        });
        const json = await res.json().catch(()=> ({}));
        if (!res.ok) throw new Error(json.message || 'Không gửi được dữ liệu');
        alert(json.message || `Đã lưu ${json.imported ?? this.rows.length} dòng.`);
        }catch(err){
        alert('Lỗi: ' + err.message);
        }finally{
        this.sending = false;
        }
    },
    // ---------- lifecycle ----------
    boot(){
        const y = new Date().getFullYear();
        this.yearOptions = [ `${y-1}-${y}`, `${y}-${y+1}`, `${y+1}-${y+2}` ];
        this.academicYear = this.yearOptions[1];

        // nạp tỉnh cho cả 2 sơ đồ (hoặc lazy-load ở watcher)
        this.loadProvinces('old');
        this.loadProvinces('new');

        // Khi đổi scheme, reset lựa chọn không dùng
        this.$watch('scheme', async (to, from) => {
            if (to === 'old') {
            // reset NEW
            this.selProvNew = ''; this.selWardNew = '';
            this.wardsNew = [];

            // nếu cần: lazy-load tỉnh cũ
            if (!this.provincesOld.length) await this.loadProvinces('old');
            } else {
            // reset OLD
            this.selProvOld = ''; this.selDistOld = ''; this.selWardOld = '';
            this.districtsOld = []; this.wardsOld = [];

            // nếu cần: lazy-load tỉnh mới
            if (!this.provincesNew.length) await this.loadProvinces('new');
            }
        });
    },

    // ---------- geo handlers (placeholder) ----------
    // --- gọi API /ajax/geo/provinces?scheme=old|new
        async loadProvinces(scheme){
        try{
            const r = await fetch(`${GEO_API.provinces}?scheme=${scheme}`, {
            headers:{ 'Accept':'application/json' },
            credentials:'same-origin'
            });
            const items = await r.json();
            if (scheme === 'old') this.provincesOld = items;
            else this.provincesNew = items;
        }catch(e){ console.error('loadProvinces', e); }
        },

        // --- gọi API /ajax/geo/children/{id}
        async fetchChildren(parentId){
        if(!parentId) return [];
        try{
            const r = await fetch(GEO_API.children(parentId), {
            headers:{ 'Accept':'application/json' },
            credentials:'same-origin'
            });
            const j = await r.json();
            return Array.isArray(j.children) ? j.children : [];
        }catch(e){ console.error('fetchChildren', e); return []; }
        },

        // ---------- geo handlers (ĐÃ fetch dữ liệu) ----------
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
            this.hamletsOld = await this.fetchChildren(this.selWardOld); // con của xã = thôn
            },
        onChangeHamletOld(e){ this.selHamletOld = e.target.value; },

            // NEW
        async onChangeWardNew(e){
            this.selWardNew = e.target.value;
            this.selHamletNew = '';
            this.hamletsNew = await this.fetchChildren(this.selWardNew);
            },
        onChangeHamletNew(e){ this.selHamletNew = e.target.value; },

        async onChangeProvNew(e){
            this.selProvNew = e.target.value;
            this.selWardNew = ''; this.selHamletNew = '';
            this.wardsNew = await this.fetchChildren(this.selProvNew);
            this.hamletsNew = [];
        },
        

    // ---------- file handling ----------
    onFileChange(e){
        const file = e.target.files?.[0]; if(!file) return;
        this.fileName = file.name;
        this.loading = true;

        const reader = new FileReader();

        reader.onload = ev => {
            // Nhường 1 tick để overlay hiển thị trước khi parse nặng
            this.$nextTick(() => {
            setTimeout(() => {
                try {
                const data = new Uint8Array(ev.target.result);
                this.wb = XLSX.read(data, { type:'array' });     // parse Excel (đồng bộ, có thể nặng)
                this.sheetNames = this.wb.SheetNames || [];
                this.selectedSheet = this.sheetNames[0] || '';
                this.extractSheet();                             // tách header, mapping, dữ liệu
                } catch (err) {
                console.error(err);
                alert('Không đọc được tệp Excel. Vui lòng kiểm tra lại.');
                } finally {
                this.loading = false;
                }
            }, 10);
            });
        };

        reader.onerror = () => {
            this.loading = false;
            alert('Lỗi khi đọc file. Vui lòng thử lại.');
        };

        reader.readAsArrayBuffer(file);
    },

    extractSheet(){
      if(!this.wb || !this.selectedSheet) return;
      const ws = this.wb.Sheets[this.selectedSheet];

      // 1) Lấy dữ liệu 2D chuẩn chiều rộng
      const rows2D = getSheetRows(ws);

      // 2) Tự động tìm hàng số thứ tự + header ngay phía trên
      let numberIdx = detectNumberRowIndex(rows2D);
      let headerIdx = numberIdx > 0 ? numberIdx - 1 : HEADER_ROW_INDEX;
      if (numberIdx < 0) { numberIdx = NUMBER_ROW_INDEX; headerIdx = HEADER_ROW_INDEX; }

      // 3) Header duy nhất + mapping từ hàng số thứ tự
      const headersRaw = (rows2D[headerIdx] || []).map(h => String(h||'').trim());
      this.headers = makeUniqueHeaders(headersRaw);
      const numberRow = rows2D[numberIdx] || [];
      this.mapping = buildMapFromNumberRow(this.headers, numberRow);

      // 4) Dữ liệu từ hàng sau số thứ tự
      const dataRows = rows2D.slice(numberIdx + 1);
      this.rows = dataRows.map(arr => {
        const o = {};
        this.headers.forEach((h,i)=> o[h] = arr[i]);
        return o;
      });
      this.rowCount = this.rows.length;

      // lưu lại để nút "Ánh xạ..." tái sử dụng
      this._lastNumberRowIndex = numberIdx;
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

    // ---------- grouping & mapping ----------
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

        // Các field boolean
        const boolKeys = [
            'is_female', 'is_bo_tuc',
            // toàn bộ field bắt đầu bằng 'dis_' (KT vận động... Có chứng nhận, Khả năng học)
            ...this.fields.filter(f => f.key.startsWith('dis_')).map(f => f.key),
        ];
        boolKeys.forEach(k => r[k] = toBool(r[k]));

        // Các field số nguyên nhẹ
        ['dob_day','dob_month','dob_year','hoc_xong_lop','bo_hoc_lop'].forEach(k=>{
            const v = parseInt(r[k],10); r[k] = isNaN(v) ? '' : v;
        });
        if (r.row_no !== undefined){
            const n = parseInt(r.row_no,10); r.row_no = isNaN(n) ? '' : n;
        }
        return r;
        },
    mapAll(){ return this.rows.map(r => this.mapOne(r)); },

    previewMapped(){ return this.mapAll().slice(0, 50); },

    // ---------- meta & output ----------
    applyMeta(row){
      const geo = this.scheme === 'old'
        ? { scheme:'old', province_id:this.selProvOld||'', district_id:this.selDistOld||'', ward_id:this.selWardOld||'' }
        : { scheme:'new', province_id:this.selProvNew||'', ward_id:this.selWardNew||'' };
      return { ...row, academic_year: this.academicYear, geo };
    },

    downloadJSON(){
      const data = this.mapAll().map(r => this.applyMeta(r));
      const blob = new Blob([JSON.stringify(data,null,2)], {type:'application/json'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = `import_${Date.now()}.json`; a.click();
      URL.revokeObjectURL(url);
    },

    async postToAPI(){
      if(!this.api.url){ alert('Chưa cấu hình Endpoint URL'); return; }
      const payload = this.mapAll().map(r => this.applyMeta(r));
      const resp = await fetch(this.api.url, {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          ...(this.api.token ? {Authorization:`Bearer ${this.api.token}`} : {})
        },
        body: JSON.stringify(payload)
      });
      const text = await resp.text();
      alert(`API ${resp.status}: ${text.slice(0,300)}`);
    },

    formatCell(v){ if (v===true) return '✓'; if (v===false) return ''; return String(v ?? ''); },
  }
}
</script>
@endpush
