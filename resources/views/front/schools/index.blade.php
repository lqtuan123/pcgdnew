@extends('layouts.front')
@section('title','Danh mục Trường học')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div x-data="schoolsUI()" x-init="boot()" class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between">
    <h1 class="text-xl font-semibold">Danh mục Trường học</h1>
    <div class="flex items-center gap-3">
      <!-- Nút thêm thông tin trường - hiển thị cho tài khoản cấp trường -->
      <div x-show="isSchoolUser" x-cloak class="relative">
        <button @click="open = !open" 
                class="rounded-lg bg-blue-600 px-3 py-2 text-white hover:bg-blue-700 flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
          </svg>
          Thêm thông tin trường
          <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
          </svg>
        </button>
        
        <!-- Dropdown menu -->
        <div x-show="open" 
             @click.away="open = false"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="transform opacity-0 scale-95"
             x-transition:enter-end="transform opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="transform opacity-100 scale-100"
             x-transition:leave-end="transform opacity-0 scale-95"
             class="absolute right-0 mt-2 w-64 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
          <div class="py-2">
            <!-- Lựa chọn đổi loại hình - hiển thị cho tất cả tài khoản cấp trường -->
            <button @click="showConglapModal = true; open = false" 
                    class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-50 transition-colors w-full text-left">
              <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
              </svg>
              <div>
                <div class="font-medium">Đổi loại hình Công lập/Ngoài công lập</div>
              </div>
            </button>
            
            <!-- Chỉ hiển thị 2 lựa chọn khi có quyền báo cáo -->
            <template x-if="hasReportAccess">
              <div>
                <a :href="getTeacherReportUrl()" 
                   class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-50 transition-colors">
                  <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                  </svg>
                  <div>
                    <div class="font-medium">Nhập thông tin giáo viên</div>
                    <div class="text-sm text-gray-500" x-text="getTeacherReportDescription()"></div>
                  </div>
                </a>
                <a :href="getInfrastructureUrl()" 
                   class="flex items-center gap-3 px-4 py-3 text-gray-700 hover:bg-gray-50 transition-colors">
                  <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                  </svg>
                  <div>
                    <div class="font-medium">Nhập thông tin cơ sở vật chất</div>
                    <div class="text-sm text-gray-500" x-text="getInfrastructureDescription()"></div>
                  </div>
                </a>
              </div>
            </template>
            <!-- Hiển thị thông báo khi không có quyền báo cáo -->
            <template x-if="!hasReportAccess">
              <div class="px-4 py-3 text-gray-500 text-sm">
                <div class="font-medium">Không có quyền truy cập</div>
                <div class="text-xs mt-1">Chỉ dành cho trường Mầm non, Tiểu học và THCS</div>
              </div>
            </template>
          </div>
        </div>
      </div>
      
      <a :href="exportHref"
         class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700 inline-flex items-center gap-2">
        <svg x-show="hasFilter" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
        </svg>
        <span x-text="hasFilter ? 'Xuất Excel theo bộ lọc' : 'Xuất Excel'"></span>
      </a>
    </div>
  </div>

  <!-- Bộ lọc -->
  <div class="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-6">
    <div class="sm:col-span-2">
      <input type="text" x-model.trim="filters.q" placeholder="Tìm mã / tên trường…"
             class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <select x-model="filters.school_type_id" class="w-full rounded-lg border px-3 py-2">
        <option value="">— Loại hình —</option>
        @foreach($types as $t)
          <option value="{{ $t->id }}">{{ $t->name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <select x-model="filters.educational_level_id" class="w-full rounded-lg border px-3 py-2">
        <option value="">— Bậc học —</option>
        @foreach($levels as $l)
          <option value="{{ $l->id }}">{{ $l->name }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <select x-model="filters.dissolved" class="w-full rounded-lg border px-3 py-2" title="Tình trạng">
        <option value="exclude">Đang hoạt động</option>
        <option value="only">Đã giải thể</option>
        <option value="all">Tất cả</option>
      </select>
    </div>
    <div>
      <select x-model="filters.province_code_new" @change="onProvinceChange"
              class="w-full rounded-lg border px-3 py-2">
        <option value="">— Tỉnh (mới) —</option>
        <template x-for="p in provinces" :key="p.code">
          <option :value="p.code" x-text="p.name"></option>
        </template>
      </select>
    </div>
    <div>
      <select x-model="filters.ward_code_new" class="w-full rounded-lg border px-3 py-2"
              :disabled="wards.length===0">
        <option value="">— Phường/Xã (mới) —</option>
        <template x-for="w in wards" :key="w.code">
          <option :value="w.code" x-text="w.name"></option>
        </template>
      </select>
    </div>

    <div class="sm:col-span-2 flex items-center gap-2">
      <select x-model.number="perPage" class="rounded-lg border px-7 py-2">
        <option value="10">10 dòng</option>
        <option value="20">20 dòng</option>
        <option value="50">50 dòng</option>
        <option value="100">100 dòng</option>
      </select>
      <button @click="apply()" class="rounded-lg bg-cyan-600 px-3 py-2 text-white">Lọc</button>
      <button @click="clear()" class="rounded-lg bg-slate-100 px-3 py-2">Xoá</button>
    </div>
  </div>

  <!-- Bảng -->
  
    <div class="relative">
    <!-- overlay loading -->
    <div x-show="loading" x-transition.opacity x-cloak
        class="absolute inset-0 z-10 grid place-items-center bg-white/70">
        <div class="flex items-center gap-2 text-slate-600">
        <svg class="h-6 w-6 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
        </svg>
        Đang tải…
        </div>
    </div>

    <!-- Desktop/tablet ≥ md: BẢNG -->
    <div class="hidden md:block overflow-hidden rounded-xl border bg-white shadow-sm">
        <div class="overflow-x-auto"> <!-- bỏ -mx-4 -->
            <table class="min-w-full table-fixed text-sm">
            <colgroup>
                <col class="w-16">          <!-- STT -->
                <col class="w-44">          <!-- Mã -->
                <col class="w-[30rem]">     <!-- Tên trường -->
                <col class="w-48">
                <col class="w-40">
                <col class="w-64">
                <col class="w-56">
                <col class="w-40">
            </colgroup>
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                <th class="px-3 py-2 text-center">#</th>   <!-- canh giữa -->
                <th class="px-3 py-2 text-left">Mã</th>
                <th class="px-3 py-2 text-left">Tên trường</th>
                <th class="px-3 py-2 text-left">Loại hình</th>
                <th class="px-3 py-2 text-left">Bậc học</th>
                <th class="px-3 py-2 text-left">Phường/Xã (mới)</th>
                <th class="px-3 py-2 text-left">Tỉnh/Thành (mới)</th>
                <th class="px-3 py-2 text-left">Tình trạng</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                <template x-for="(r,i) in rows" :key="r.code">
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 text-center" x-text="(page-1)*perPage + i + 1"></td>
                    <td class="px-3 py-2 font-mono" x-text="r.code"></td>
                    <td class="px-3 py-2 whitespace-normal break-words align-top">
                    <div class="font-medium leading-snug" x-text="r.name"></div>
                    </td>
                    <td class="px-3 py-2" x-text="r.school_type || '—'"></td>
                    <td class="px-3 py-2" x-text="r.educational_level || '—'"></td>
                    <td class="px-3 py-2">
                    <div x-text="r.ward_new?.name || '—'"></div>
                    <div class="text-xs text-slate-400" x-text="r.ward_code_new || '—'"></div>
                    </td>
                    <td class="px-3 py-2" x-text="r.province_new?.name || '—'"></td>
                    <td class="px-3 py-2">
                    <span :class="r.is_dissolved ? 'text-rose-600' : 'text-emerald-700'"
                            x-text="r.is_dissolved ? 'Đã giải thể' : 'Đang hoạt động'"></span>
                    </td>
                </tr>
                </template>
                <tr x-show="rows.length===0">
                <td colspan="8" class="px-3 py-6 text-center text-slate-500">Không có dữ liệu</td>
                </tr>
            </tbody>
            </table>
        </div>
        </div>

    <!-- Mobile < md: CARDS -->
    <div class="md:hidden grid gap-3">
        <template x-for="(r,i) in rows" :key="r.code">
        <article class="rounded-xl border bg-white p-3 shadow-sm">
            <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-xs text-slate-500"># <span x-text="(page-1)*perPage + i + 1"></span> • 
                <span class="font-mono" x-text="r.code"></span>
                </div>
                <h3 class="mt-1 font-semibold leading-snug" x-text="r.name"></h3>
            </div>
            <span class="shrink-0 rounded-full px-2 py-1 text-xs"
                    :class="r.is_dissolved ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'"
                    x-text="r.is_dissolved ? 'Đã giải thể' : 'Đang hoạt động'"></span>
            </div>

            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <div>
                <dt class="text-slate-500">Loại hình</dt>
                <dd x-text="r.school_type || '—'"></dd>
            </div>
            <div>
                <dt class="text-slate-500">Bậc học</dt>
                <dd x-text="r.educational_level || '—'"></dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-500">Phường/Xã (mới)</dt>
                <dd>
                <span x-text="r.ward_new?.name || '—'"></span>
                <span class="ml-2 text-xs text-slate-400" x-text="r.ward_code_new || ''"></span>
                </dd>
            </div>
            <div class="col-span-2">
                <dt class="text-slate-500">Tỉnh/Thành (mới)</dt>
                <dd x-text="r.province_new?.name || '—'"></dd>
            </div>
            </dl>
        </article>
        </template>

        <div x-show="rows.length===0"
            class="rounded-xl border bg-white p-6 text-center text-slate-500">
        Không có dữ liệu
        </div>
    </div>
    </div>

 

  <!-- Pagination -->
  <div class="mt-3 flex items-center justify-between text-sm">
    <button class="rounded-lg bg-slate-100 px-3 py-2 disabled:opacity-50"
            :disabled="page<=1" @click="goto(page-1)">Trang trước</button>
    <div class="flex items-center gap-2">
      Trang
      <input type="number" min="1" :max="lastPage" x-model.number="pageInput"
             @keydown.enter.prevent="goto(pageInput)"
             class="w-16 rounded-lg border px-3 py-2">
      trên <span x-text="lastPage"></span>
    </div>
    <button class="rounded-lg bg-slate-100 px-3 py-2 disabled:opacity-50"
            :disabled="page>=lastPage" @click="goto(page+1)">Trang sau</button>
  </div>

  <!-- Modal đổi loại hình trường -->
  <div x-show="showConglapModal" 
       x-cloak
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-200"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0"
       class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
       @click.self="showConglapModal = false">
    <div x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900">Đổi loại hình trường</h3>
          <button @click="showConglapModal = false" 
                  class="text-gray-400 hover:text-gray-600 transition-colors">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tên trường</label>
            <div class="p-3 bg-gray-50 rounded-lg text-sm text-gray-600" x-text="userSchoolName"></div>
          </div>
          
          <div>
            <label class="flex items-center gap-3">
              <input type="checkbox" 
                     x-model="isConglapChecked"
                     class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 focus:ring-2">
              <span class="text-sm font-medium text-gray-700">Trường công lập</span>
            </label>
            <p class="text-xs text-gray-500 mt-1">
              Bỏ chọn nếu trường là ngoài công lập
            </p>
          </div>
        </div>
        
        <div class="flex items-center justify-end gap-3 mt-6">
          <button @click="showConglapModal = false" 
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
            Hủy
          </button>
          <button @click="updateConglapStatus()" 
                  :disabled="updatingConglap"
                  class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2">
            <svg x-show="updatingConglap" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
            </svg>
            <span x-text="updatingConglap ? 'Đang cập nhật...' : 'Xác nhận'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
function schoolsUI(){
  const DATA_URL = @json(route('front.schools.data'));
  const GEO_URL  = @json(route('front.geo.data'));   // đã có ở trang “địa chỉ mới”

  async function fetchJson(url, params={}){
    const u = new URL(url, window.location.origin);
    Object.entries(params).forEach(([k,v])=>{
      if (v!=='' && v!==null && v!==undefined) u.searchParams.set(k, v);
    });
    const res = await fetch(u.toString(), { headers: {Accept:'application/json'} });
    if(!res.ok) throw new Error('API '+res.status);
    return res.json();
  }

  return {
    loading: false,
    rows: [], page: 1, pageInput: 1, lastPage: 1, perPage: 20,
    provinces: [], wards: [],
    isSchoolUser: @json(auth()->user()->isSchool() ?? false),
    userSchoolLevel: @json(auth()->user()->school?->educationalLevel?->name ?? null),
    userSchoolName: @json(auth()->user()->school?->name ?? ''),
    userSchoolId: @json(auth()->user()->school?->id ?? null),
    hasReportAccess: false,
    showConglapModal: false,
    isConglapChecked: @json(auth()->user()->school?->is_conglap ?? true),
    updatingConglap: false,
    open: false,
    filters: {
      q: '',
      school_type_id: '',
      educational_level_id: '',
      dissolved: 'exclude',             // exclude|only|all
      province_code_new: '',
      ward_code_new: ''
    },

    get hasFilter(){
      return this.filters.q !== ''
        || this.filters.school_type_id !== ''
        || this.filters.educational_level_id !== ''
        || this.filters.dissolved !== 'exclude'
        || this.filters.province_code_new !== ''
        || this.filters.ward_code_new !== '';
    },

    get exportHref(){
      const u = new URL(@json(route('front.schools.export')), window.location.origin);
      const params = {
        q: this.filters.q,
        school_type_id: this.filters.school_type_id,
        educational_level_id: this.filters.educational_level_id,
        dissolved: this.filters.dissolved,
        province_code_new: this.filters.province_code_new,
        ward_code_new: this.filters.ward_code_new
      };
      Object.entries(params).forEach(([k,v])=>{
        if(v!=='' && v!==null && v!==undefined) u.searchParams.set(k,v);
      });
      return u.toString();
    },

    async boot(){
      this.checkReportAccess();
      // nạp tỉnh (mới)
      const p = await fetchJson(GEO_URL, { level:1, per_page:200, page:1 });
      this.provinces = p.data || [];
      await this.fetch(); // lần đầu
    },

    // Kiểm tra quyền truy cập báo cáo dựa trên cấp học
    checkReportAccess() {
      if (!this.userSchoolLevel) {
        this.hasReportAccess = false;
        return;
      }
      
      const level = this.userSchoolLevel.toLowerCase();
      
      // Chỉ cho phép các cấp MN, TH, THCS
      if (level.includes('mầm non') || 
          level.includes('mam non') ||
          level.includes('tiểu học') || 
          level.includes('tieu hoc') ||
          level.includes('thcs') || 
          level.includes('trung học cơ sở')) {
        this.hasReportAccess = true;
      } else {
        this.hasReportAccess = false;
      }
    },

    async onProvinceChange(){
      this.filters.ward_code_new = '';
      this.wards = [];
      if (this.filters.province_code_new) {
        const w = await fetchJson(GEO_URL, {
          level: 3,
          province_code: this.filters.province_code_new,
          per_page: 200,
          page: 1
        });
        this.wards = w.data || [];
      }
    },

    async fetch(){
      this.loading = true;
      try {
        const js = await fetchJson(DATA_URL, {
          page: this.page,
          per_page: this.perPage,
          q: this.filters.q,
          school_type_id: this.filters.school_type_id,
          educational_level_id: this.filters.educational_level_id,
          dissolved: this.filters.dissolved,
          province_code_new: this.filters.province_code_new,
          ward_code_new: this.filters.ward_code_new
        });
        this.rows = js.data || [];
        this.page = js.current_page; this.pageInput = this.page;
        this.lastPage = js.last_page; this.perPage = js.per_page;
      } finally {
        this.loading = false;
      }
    },

    async goto(p){
      if (p<1) p=1; if (p>this.lastPage) p=this.lastPage;
      this.page = p; this.pageInput = p;
      await this.fetch();
    },

    async apply(){ this.page = 1; await this.fetch(); },
    async clear(){
      this.filters.q='';
      this.filters.school_type_id='';
      this.filters.educational_level_id='';
      this.filters.dissolved='exclude';
      this.filters.province_code_new='';
      this.filters.ward_code_new='';
      this.wards=[];
      this.page=1;
      await this.fetch();
    },

    // Xác định URL báo cáo giáo viên dựa trên cấp học
    getTeacherReportUrl() {
      if (!this.userSchoolLevel) return '#';
      
      const level = this.userSchoolLevel.toLowerCase();
      
      // Kiểm tra mầm non
      if (level.includes('mầm non') || level.includes('mam non')) {
        return @json(route('front.kindergarten-reports.index'));
      }
      
      // Kiểm tra tiểu học
      if (level.includes('tiểu học') || level.includes('tieu hoc')) {
        return @json(route('front.teacher-reports.index'));
      }
      
      // Kiểm tra THCS
      if (level.includes('thcs') || level.includes('trung học cơ sở')) {
        return @json(route('front.secondary-teacher-reports.index'));
      }
      
      // Mặc định trả về tiểu học nếu không xác định được
      return @json(route('front.teacher-reports.index'));
    },

    // Lấy mô tả cho báo cáo giáo viên
    getTeacherReportDescription() {
      if (!this.userSchoolLevel) return 'Thêm, cập nhật thông tin giáo viên';
      
      const level = this.userSchoolLevel.toLowerCase();
      
      if (level.includes('mầm non') || level.includes('mam non')) {
        return 'Báo cáo đội ngũ giáo viên Mầm non';
      }
      
      if (level.includes('tiểu học') || level.includes('tieu hoc')) {
        return 'Báo cáo đội ngũ giáo viên Tiểu học';
      }
      
      if (level.includes('thcs') || level.includes('trung học cơ sở')) {
        return 'Báo cáo đội ngũ giáo viên THCS';
      }
      
      return 'Thêm, cập nhật thông tin giáo viên';
    },

    // Xác định URL báo cáo cơ sở vật chất dựa trên cấp học
    getInfrastructureUrl() {
      if (!this.userSchoolLevel) return '#';
      
      const level = this.userSchoolLevel.toLowerCase();
      
      // Kiểm tra mầm non
      if (level.includes('mầm non') || level.includes('mam non')) {
        return @json(route('front.kindergarten-infrastructure.index'));
      }
      
      // Kiểm tra tiểu học
      if (level.includes('tiểu học') || level.includes('tieu hoc')) {
        return @json(route('front.elementary-infrastructure.index'));
      }
      
      // Kiểm tra THCS
      if (level.includes('thcs') || level.includes('trung học cơ sở')) {
        return @json(route('front.primary-school-infrastructure.index'));
      }
      
      // Mặc định trả về tiểu học nếu không xác định được
      return @json(route('front.elementary-infrastructure.index'));
    },

    // Lấy mô tả cho báo cáo cơ sở vật chất
    getInfrastructureDescription() {
      if (!this.userSchoolLevel) return 'Thêm, cập nhật thông tin cơ sở vật chất';
      
      const level = this.userSchoolLevel.toLowerCase();
      
      if (level.includes('mầm non') || level.includes('mam non')) {
        return 'Báo cáo cơ sở vật chất trường Mầm non';
      }
      
      if (level.includes('tiểu học') || level.includes('tieu hoc')) {
        return 'Báo cáo cơ sở vật chất trường Tiểu học';
      }
      
      if (level.includes('thcs') || level.includes('trung học cơ sở')) {
        return 'Báo cáo cơ sở vật chất trường THCS';
      }
      
      return 'Thêm, cập nhật thông tin cơ sở vật chất';
    },

    // Cập nhật trạng thái công lập
    async updateConglapStatus() {
      if (!this.userSchoolId) {
        alert('Không tìm thấy thông tin trường');
        return;
      }

      this.updatingConglap = true;
      try {
        const response = await fetch(`/truong/${this.userSchoolId}/update-conglap`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            is_conglap: this.isConglapChecked
          })
        });

        const result = await response.json();

        if (response.ok) {
          this.showConglapModal = false;
          alert('Cập nhật loại hình trường thành công!');
        } else {
          alert(result.message || 'Có lỗi xảy ra khi cập nhật');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi cập nhật');
      } finally {
        this.updatingConglap = false;
      }
    },
  };
}
</script>
@endpush
