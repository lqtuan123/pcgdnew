@extends('layouts.front')
@section('title','Đơn vị hành chính (Địa chỉ mới)')

@section('content')
<style>[x-cloak]{display:none!important}</style>

<div x-data="geoUI()" x-init="boot()" class="mx-auto max-w-7xl px-4 py-6">
  <h1 class="mb-4 text-xl font-semibold">
    Danh mục Đơn vị hành chính – <span class="text-cyan-700">Địa chỉ mới</span>
  </h1>
  
  {{-- Thông báo filter theo user --}}
  <div class="mb-4" x-show="userWard || (userProvince && userInfo.role_level === 'province')" x-cloak>
    <div class="rounded-lg bg-blue-50 border border-blue-200 p-3">
      <div class="flex items-center">
        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div class="text-blue-800 text-sm">
          <span x-show="userWard">
            Dữ liệu được lọc theo phường/xã: <strong x-text="userWard.name"></strong>
          </span>
          <span x-show="!userWard && userProvince && userInfo.role_level === 'province'">
            Dữ liệu được lọc theo tỉnh/thành: <strong x-text="userProvince.name"></strong>
          </span>
        </div>
      </div>
    </div>
  </div>
  


  {{-- Tabs: C1, C3, C4 --}}
  <div class="mb-4 inline-flex rounded-xl border bg-white p-1 shadow-sm">
    <button @click="switchTab(1)"
      :class="tab===1 ? 'bg-cyan-600 text-white' : 'text-slate-700 hover:bg-slate-50'"
      class="rounded-lg px-4 py-2 text-sm font-medium">Tỉnh/Thành (C1)</button>

    <button @click="switchTab(3)"
      :class="tab===3 ? 'bg-cyan-600 text-white' : 'text-slate-700 hover:bg-slate-50'"
      class="rounded-lg px-4 py-2 text-sm font-medium">Phường/Xã (C3)</button>

    <button @click="switchTab(4)"
      :class="tab===4 ? 'bg-cyan-600 text-white' : 'text-slate-700 hover:bg-slate-50'"
      class="rounded-lg px-4 py-2 text-sm font-medium">Tổ dân phố / Thôn (C4)</button>
  </div>

  {{-- Bộ lọc cha cho C3/C4 (không còn quận/huyện) --}}
  <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-2" x-show="tab!==1" x-cloak>
    <div>
      <label class="mb-1 block text-xs text-slate-500">
        Chọn Tỉnh/Thành
        <span x-show="!canChangeProvince()" class="text-gray-500">(đã khóa theo tài khoản)</span>
      </label>
      <select x-model="filters.province_code" @change="onProvinceChange()"
              :disabled="!canChangeProvince()"
              class="w-full rounded-lg border px-3 py-2"
              :class="!canChangeProvince() ? 'bg-gray-100 cursor-not-allowed' : ''">
        <option value="" x-show="canChangeProvince()">— Tất cả —</option>
        <template x-for="p in opts.provinces" :key="p.code">
          <option :value="p.code" x-text="p.name"></option>
        </template>
      </select>
    </div>

    <div x-show="tab===4" x-cloak>
      <label class="mb-1 block text-xs text-slate-500">
        Chọn Phường/Xã 
        <span x-show="!canChangeWard()" class="text-gray-500">(đã khóa theo tài khoản)</span>
        <span x-show="canChangeWard()" class="text-gray-500">(tuỳ chọn)</span>
      </label>
      <select x-model="filters.ward_code" @change="fetch()"
              :disabled="!canChangeWard()"
              class="w-full rounded-lg border px-3 py-2"
              :class="!canChangeWard() ? 'bg-gray-100 cursor-not-allowed' : ''">
        <option value="" x-show="canChangeWard()">— Tất cả —</option>
        <template x-for="w in opts.wards" :key="w.code">
          <option :value="w.code" x-text="w.name"></option>
        </template>
      </select>
    </div>
  </div>

  {{-- Search + page size + Thêm thôn/xóm button --}}
  <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
    <input type="text" x-model="filters.code" placeholder="Lọc theo mã"
      class="rounded-lg border px-3 py-2 outline-none focus:ring-2 focus:ring-cyan-500">
    <input type="text" x-model="filters.name" placeholder="Lọc theo tên"
      class="rounded-lg border px-3 py-2 outline-none focus:ring-2 focus:ring-cyan-500">
    <div class="flex items-center gap-2">
      <select x-model="perPage" @change="goto(1)" class="rounded-lg border px-6 py-2">
        <option value="10">10 dòng</option>
        <option value="20">20 dòng</option>
        <option value="50">50 dòng</option>
        <option value="100">100 dòng</option>
      </select>
      <button @click="applyFilters()" class="rounded-lg bg-cyan-600 px-3 py-2 text-white">Tìm</button>
      <button @click="clearFilters()" class="rounded-lg bg-slate-100 px-3 py-2">Xóa</button>
    </div>
    {{-- Nút thêm thôn/xóm chỉ hiện ở tab cấp 4 --}}
    <div class="flex justify-end" x-show="tab===4" x-cloak>
      <button @click="openCreateHamletModal()" 
              class="rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700 flex items-center gap-2"
              :disabled="!canCreateHamlet()">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Thêm thôn/xóm
      </button>
    </div>
  </div>

  {{-- Table --}}
  <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-slate-600">
        <tr>
          <th class="w-16 px-3 py-2 text-left">STT</th>
          <th class="w-40 px-3 py-2 text-left">Mã</th>
          <th class="px-3 py-2 text-left">Tên đơn vị</th>
          <th class="w-64 px-3 py-2 text-left" x-show="tab===3 || tab===4">Tỉnh/Thành</th>
          <th class="w-40 px-3 py-2 text-left" x-show="tab===4 && canCreateHamlet()">Thao tác</th>
        </tr>
      </thead>
      <tbody class="[&>tr:nth-child(even)]:bg-slate-50/40">
        <template x-for="(row, i) in rows" :key="row.code">
          <tr class="border-t">
            <td class="px-3 py-2" x-text="(page-1)*perPage + i + 1"></td>
            <td class="px-3 py-2 font-mono" x-text="row.code"></td>

            {{-- C1: click để qua C3 theo tỉnh | C3: click để qua C4 theo xã --}}
            <td class="px-3 py-2">
              <span
                :class="(tab===1 || tab===3) ? 'text-blue-700 hover:underline cursor-pointer' : ''"
                @click="if (tab === 1) { goToWardByProvince(row) } else if (tab === 3) { goToHamletByWard(row) }"
                x-text="row.name">
              </span>
            </td>

            {{-- Cột Tỉnh/Thành ở C3/C4 --}}
            <td class="px-3 py-2" x-show="tab===3 || tab===4" x-text="row.province ? row.province.name : '-'"></td>
            
            {{-- Cột Thao tác ở C4 (chỉ hiện nếu có quyền) --}}
            <td class="px-3 py-2" x-show="tab===4 && canCreateHamlet()">
              <div class="flex gap-1">
                <button @click="openEditHamletModal(row)" 
                        class="text-blue-600 hover:text-blue-800 text-sm px-2 py-1 rounded hover:bg-blue-50"
                        title="Sửa thôn/xóm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                  </svg>
                </button>
                <button @click="confirmDeleteHamlet(row)" 
                        class="text-red-600 hover:text-red-800 text-sm px-2 py-1 rounded hover:bg-red-50"
                        title="Xóa thôn/xóm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </template>
        <tr x-show="rows.length===0">
          <td :colspan="tab===4 && canCreateHamlet() ? '5' : '4'" class="px-3 py-6 text-center text-slate-500">Không có dữ liệu</td>
        </tr>
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
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

  {{-- Modal tạo thôn/xóm mới --}}
  <div x-show="showCreateModal" x-cloak 
       class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
       @click.self="closeCreateModal()">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Thêm thôn/xóm mới</h3>
      </div>
      
      <form @submit.prevent="submitCreateHamlet()" class="px-6 py-4 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phường/Xã</label>
          <select x-model="createForm.ward_id" required class="w-full rounded-lg border px-3 py-2">
            <option value="">-- Chọn phường/xã --</option>
            <template x-for="w in getUserWardsForCreate()" :key="w.id">
              <option :value="w.id" x-text="w.name"></option>
            </template>
          </select>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tên thôn/xóm</label>
          <input type="text" x-model="createForm.name" required
                 class="w-full rounded-lg border px-3 py-2" 
                 placeholder="Ví dụ: Thôn 1, Tổ dân phố 2">
        </div>
        
        <!-- Mã sẽ được sinh tự động ở backend; không hiển thị ô nhập -->
        
        <div class="flex justify-end gap-2 pt-4 border-t">
          <button type="button" @click="closeCreateModal()"
                  class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">
            Hủy
          </button>
          <button type="submit" :disabled="isCreating"
                  class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
            <span x-show="!isCreating">Tạo mới</span>
            <span x-show="isCreating">Đang tạo...</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Modal sửa thôn/xóm --}}
  <div x-show="showEditModal" x-cloak 
       class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
       @click.self="closeEditModal()">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b">
        <h3 class="text-lg font-semibold">Sửa thôn/xóm</h3>
      </div>
      
      <form @submit.prevent="submitEditHamlet()" class="px-6 py-4 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tên thôn/xóm</label>
          <input type="text" x-model="editForm.name" required
                 class="w-full rounded-lg border px-3 py-2" 
                 placeholder="Ví dụ: Thôn 1, Tổ dân phố 2">
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mã</label>
          <input type="text" x-model="editForm.code" required
                 class="w-full rounded-lg border px-3 py-2" 
                 placeholder="Ví dụ: T01, TDP02">
        </div>
        
        <div class="flex justify-end gap-2 pt-4 border-t">
          <button type="button" @click="closeEditModal()"
                  class="px-4 py-2 text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">
            Hủy
          </button>
          <button type="submit" :disabled="isEditing"
                  class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            <span x-show="!isEditing">Cập nhật</span>
            <span x-show="isEditing">Đang cập nhật...</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
function geoUI() {
  const DATA_PATH = '{{ route('front.geo.data') }}';
  const BASE_URL  = new URL(DATA_PATH, window.location.origin).toString();

  async function apiFetch(params = {}) {
    const u = new URL(BASE_URL);
    Object.entries(params).forEach(([k,v])=>{
      if (v !== undefined && v !== null && v !== '') u.searchParams.set(k, v);
    });
    const res  = await fetch(u.toString(), {
      headers: { 'Accept':'application/json','X-Requested-With':'XMLHttpRequest' },
      credentials: 'same-origin'
    });
    const text = await res.text();
    if (!res.ok) { console.error('API error', res.status, u.toString(), text.slice(0,300)); throw new Error('API ' + res.status); }
    const ct = res.headers.get('content-type') || '';
    if (!ct.includes('application/json')) { console.error('Not JSON', u.toString(), text.slice(0,200)); throw new Error('NOT_JSON'); }
    return JSON.parse(text);
  }

  return {
    tab: 1,                               // 1: C1; 3: C3; 4: C4
    rows: [],
    page: 1, lastPage: 1, perPage: 50, pageInput: 1,
    filters: { code:'', name:'', province_code:'', ward_code:'' },
    opts: { provinces:[], wards:[] },
    
    // Modal tạo thôn/xóm
    showCreateModal: false,
    isCreating: false,
    createForm: {
      ward_id: '',
      name: ''
    },
    
    // Modal sửa thôn/xóm
    showEditModal: false,
    isEditing: false,
    editForm: {
      hamlet_id: '',
      name: '',
      code: ''
    },
    
    userInfo: @json(auth()->user()),
    userWard: @json($userWard ?? null),
    userProvince: @json($userProvince ?? null),

    async boot(){
      await this.loadProvinces();
      await this.initUserFilters();
      
      // Force update dropdown sau một chút để đảm bảo DOM đã render
      setTimeout(() => {
        if (this.userWard) {
          this.filters.ward_code = String(this.userWard.code);
        }
      }, 100);
      
      await this.fetch();
    },
    
    async initUserFilters() {
      // Tự động set filter theo thông tin user
      if (this.userWard) {
        // User có ward_id - khóa theo ward
        this.filters.province_code = this.userProvince?.code || '';
        
        // Load danh sách ward cho dropdown (chỉ có ward của user)
        this.opts.wards = [{
          id: this.userWard.id,
          code: String(this.userWard.code), // Đảm bảo là string
          name: this.userWard.name
        }];
        
        // Set ward_code sau khi opts.wards đã được load
        this.filters.ward_code = String(this.userWard.code);
        

      } else if (this.userProvince && this.userInfo.role_level === 'province') {
        // User cấp tỉnh - khóa theo tỉnh
        this.filters.province_code = this.userProvince.code;
        // Load tất cả ward trong tỉnh
        await this.onProvinceChange();
        

      }
    },

    async fetch(){
      const q = (this.filters.code || this.filters.name || '').trim();
      const js = await apiFetch({
        level: this.tab, page: this.page, per_page: this.perPage,
        q, province_code: this.filters.province_code,
        ward_code: this.filters.ward_code
      });
      this.rows = js.data || [];
      this.page = js.current_page; this.pageInput = this.page;
      this.lastPage = js.last_page; this.perPage = js.per_page;
    },

    async loadProvinces(){
      const js = await apiFetch({ level: 1, per_page: 200, page: 1 });
      this.opts.provinces = js.data || [];
    },

    async onProvinceChange(){
      // Khi chọn tỉnh: nạp danh sách xã cho tab C4, đồng thời refresh bảng hiện tại
      this.filters.ward_code = '';
      if (this.filters.province_code) {
        const js = await apiFetch({ level: 3, province_code: this.filters.province_code, per_page: 200, page: 1 });
        this.opts.wards = js.data || [];
      } else {
        this.opts.wards = [];
      }
      await this.goto(1);
    },

    switchTab(t){ this.tab = t; this.goto(1); },

    // C1 -> C3 theo tỉnh
    async goToWardByProvince(provinceRow){
      this.filters.province_code = provinceRow.code;
      this.filters.ward_code     = '';
      await this.onProvinceChange();   // tải sẵn danh sách xã cho C4
      this.switchTab(3);
    },

    // C3 -> C4 theo phường/xã (hiện tổ/thon của phường đó)
    async goToHamletByWard(wardRow){
      // Đảm bảo đã biết tỉnh của xã (API data đã trả province.code)
      if (!this.filters.province_code && wardRow.province?.code) {
        this.filters.province_code = wardRow.province.code;
      }
      // Cập nhật dropdown xã (cho C4) & chọn đúng xã
      if (this.filters.province_code) {
        const js = await apiFetch({ level: 3, province_code: this.filters.province_code, per_page: 200, page: 1 });
        this.opts.wards = js.data || [];
      }
      this.filters.ward_code = wardRow.code;

      // Chuyển sang tab C4 và fetch danh sách tổ/thon thuộc xã
      this.switchTab(4);
    },

    async goto(p){
      if (p<1) p=1; if (p>this.lastPage) p=this.lastPage;
      this.page = p; this.pageInput = p;
      await this.fetch();
    },

    applyFilters(){ this.goto(1); },
    clearFilters(){ 
      this.filters.code=''; 
      this.filters.name=''; 
      // Không xóa các filter cố định theo user
      if (!this.userWard && !this.userProvince) {
        this.filters.province_code = '';
        this.filters.ward_code = '';
      }
      this.goto(1); 
    },
    
    // Kiểm tra user có thể thay đổi filter tỉnh không
    canChangeProvince() {
      return this.userInfo.role_level === 'admin';
    },
    
    // Kiểm tra user có thể thay đổi filter ward không  
    canChangeWard() {
      return this.userInfo.role_level === 'admin' || this.userInfo.role_level === 'province';
    },

    // Methods cho modal tạo thôn/xóm
    canCreateHamlet() {
      if (!this.userInfo) return false;
      // Admin có thể tạo mọi nơi
      if (this.userInfo.role_level === 'admin') return true;
      // Province user có thể tạo trong tỉnh của họ
      if (this.userInfo.role_level === 'province' && this.filters.province_code) return true;
      // Ward user hoặc School user có thể tạo trong phường của họ
      if ((this.userInfo.role_level === 'ward' || this.userInfo.role_level === 'school') && this.filters.ward_code) return true;
      return false;
    },

    getUserWardsForCreate() {
      if (!this.canCreateHamlet()) return [];
      
      if (this.userInfo.role_level === 'ward' || this.userInfo.role_level === 'school') {
        // Ward user hoặc School user chỉ thấy phường/xã của họ
        return this.opts.wards.filter(w => w.id == this.userInfo.ward_id);
      }
      
      // Admin hoặc Province user thấy tất cả wards trong filters
      return this.opts.wards;
    },

    openCreateHamletModal() {
      if (!this.canCreateHamlet()) {
        alert('Bạn không có quyền tạo thôn/xóm');
        return;
      }

      // Reset form (mã sẽ tự sinh phía server)
      this.createForm = {
        ward_id: this.userInfo.role_level === 'ward' ? this.userInfo.ward_id : '',
        name: ''
      };
      
      this.showCreateModal = true;
    },

    closeCreateModal() {
      this.showCreateModal = false;
      this.isCreating = false;
    },

    async submitCreateHamlet() {
      if (this.isCreating) return;
      
      this.isCreating = true;
      
      try {
        const formData = new FormData();
        formData.append('ward_id', this.createForm.ward_id);
        formData.append('name', this.createForm.name);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        const response = await fetch('{{ route('front.geo.create-hamlet') }}', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        });
        
        let result;
        try {
          result = await response.json();
        } catch (e) {
          throw new Error('Phản hồi không hợp lệ từ server');
        }
        
        if (response.ok && result.success) {
          alert(result.message);
          this.closeCreateModal();
          // Refresh danh sách nếu đang ở tab 4
          if (this.tab === 4) {
            await this.fetch();
          }
        } else {
          // Xử lý lỗi validation
          if (result.errors) {
            const errorMessages = Object.values(result.errors).flat().join('\n');
            alert('Lỗi nhập liệu:\n' + errorMessages);
          } else {
            alert(result.message || 'Có lỗi xảy ra khi tạo thôn/xóm');
          }
        }
      } catch (error) {
        console.error('Error creating hamlet:', error);
        alert('Có lỗi xảy ra khi tạo thôn/xóm');
      } finally {
        this.isCreating = false;
      }
    },

    openEditHamletModal(hamlet) {
      if (!this.canCreateHamlet()) {
        alert('Bạn không có quyền sửa thôn/xóm');
        return;
      }

      // Reset form và load dữ liệu
      this.editForm = {
        hamlet_id: hamlet.id,
        name: hamlet.name,
        code: hamlet.code
      };
      
      this.showEditModal = true;
    },

    closeEditModal() {
      this.showEditModal = false;
      this.isEditing = false;
    },

    async submitEditHamlet() {
      if (this.isEditing) return;
      
      this.isEditing = true;
      
      try {
        const formData = new FormData();
        formData.append('hamlet_id', this.editForm.hamlet_id);
        formData.append('name', this.editForm.name);
        formData.append('code', this.editForm.code);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        formData.append('_method', 'PUT');
        
        const response = await fetch('{{ route('front.geo.update-hamlet') }}', {
          method: 'POST', // Laravel form method spoofing
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        });
        
        let result;
        try {
          result = await response.json();
        } catch (e) {
          throw new Error('Phản hồi không hợp lệ từ server');
        }
        
        if (response.ok && result.success) {
          alert(result.message);
          this.closeEditModal();
          // Refresh danh sách
          await this.fetch();
        } else {
          // Xử lý lỗi validation
          if (result.errors) {
            const errorMessages = Object.values(result.errors).flat().join('\n');
            alert('Lỗi nhập liệu:\n' + errorMessages);
          } else {
            alert(result.message || 'Có lỗi xảy ra khi cập nhật thôn/xóm');
          }
        }
      } catch (error) {
        console.error('Error editing hamlet:', error);
        alert('Có lỗi xảy ra khi sửa thôn/xóm');
      } finally {
        this.isEditing = false;
      }
    },

    confirmDeleteHamlet(hamlet) {
      const hamletType = hamlet.name.toLowerCase().includes('thôn') || hamlet.name.toLowerCase().includes('buôn') ? 'thôn' : 'tổ dân phố';
      if (confirm(`Bạn có chắc chắn muốn xóa ${hamletType} "${hamlet.name}"?\n\nHành động này không thể hoàn tác!`)) {
        this.deleteHamlet(hamlet.id);
      }
    },

    async deleteHamlet(hamletId) {
      try {
        const formData = new FormData();
        formData.append('hamlet_id', hamletId);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        formData.append('_method', 'DELETE');
        
        const response = await fetch('{{ route('front.geo.delete-hamlet') }}', {
          method: 'POST', // Laravel form method spoofing
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          }
        });
        
        const result = await response.json();
        
        if (result.success) {
          alert(result.message);
          // Refresh danh sách
          await this.fetch();
        } else {
          alert('Lỗi: ' + result.message);
        }
      } catch (error) {
        console.error('Error deleting hamlet:', error);
        alert('Có lỗi xảy ra khi xóa thôn/xóm');
      }
    },
  }
}
</script>
@endpush
