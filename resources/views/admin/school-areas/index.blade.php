@extends('layouts.admin')

@section('title', 'Quản lý khu vực tuyển sinh')
@section('crumb', 'Khu vực tuyển sinh')

@section('content')
<style>
[x-cloak]{display:none!important}
.btn-danger {
  @apply bg-rose-600 text-white px-3 py-2 rounded-lg hover:bg-rose-700 disabled:bg-rose-300 disabled:cursor-not-allowed;
}
</style>

<div class="space-y-6" x-data="schoolAreasManager()" x-init="init()">
  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-bold text-slate-800">Khu vực tuyển sinh</h1>
      <p class="text-slate-600 mt-1">{{ $school->name }} ({{ $school->code }})</p>
    </div>
    <a href="{{ route('admin.schools.show', $school) }}" class="btn-soft">← Quay lại</a>
  </div>

  <!-- School Info Card -->
  <article class="card">
    <div class="grid md:grid-cols-3 gap-4 text-sm">
      <div>
        <div class="text-slate-500">Mã trường</div>
        <div class="font-medium">{{ $school->code }}</div>
      </div>
      <div>
        <div class="text-slate-500">Tên trường</div>
        <div class="font-medium">{{ $school->name }}</div>
      </div>
      <div>
        <div class="text-slate-500">Loại hình</div>
        <div>{{ optional($school->schoolType)->name ?: '—' }}</div>
      </div>
    </div>
  </article>

  <!-- Year Selector -->
  <article class="card">
    <div class="flex items-center gap-4">
      <label class="text-sm font-medium text-slate-700">Năm tuyển sinh:</label>
      <select x-model="selectedYear" @change="loadAreas()" class="rounded-lg border px-3 py-2" 
              x-init="selectedYear = new Date().getFullYear()">
        <template x-for="year in yearOptions" :key="year">
          <option :value="year" x-text="year" :selected="year === new Date().getFullYear()"></option>
        </template>
      </select>
      <div class="text-sm text-slate-500" x-text="`Đã chọn: ${areas.length} khu vực`"></div>
    </div>
  </article>

  <!-- Add Areas Section -->
  <article class="card">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Thêm khu vực tuyển sinh</h2>
      <button type="button" @click="showAddForm = !showAddForm" 
              class="btn-primary" :class="showAddForm ? 'bg-slate-600' : ''">
        <i class="fas fa-plus mr-2"></i>
        <span x-text="showAddForm ? 'Đóng' : 'Thêm khu vực'"></span>
      </button>
    </div>

    <!-- Add Form -->
    <div x-show="showAddForm" x-cloak class="border-t pt-4">
      <div class="grid md:grid-cols-2 gap-6">
        <!-- Geo Selection -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Chọn địa giới hành chính</label>
          
          <!-- Scheme Selection -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-slate-700 mb-2">Chọn theo</label>
            <select x-model="geoMode" @change="onModeChanged()" class="w-full rounded-lg border px-3 py-2">
              <option value="old">Địa giới CŨ (tỉnh→quận→phường→thôn)</option>
              <option value="new">Địa giới MỚI (tỉnh→cấp 3→thôn)</option>
            </select>
            <div class="text-xs text-slate-500 mt-1">
              <span x-show="selectedYear >= 2025" class="text-green-600">
                <i class="fas fa-info-circle mr-1"></i>Tự động chọn địa giới MỚI cho năm <span x-text="selectedYear"></span>
              </span>
              <span x-show="selectedYear < 2025" class="text-blue-600">
                <i class="fas fa-info-circle mr-1"></i>Tự động chọn địa giới CŨ cho năm <span x-text="selectedYear"></span>
              </span>
            </div>
          </div>

          <!-- OLD Scheme: Province → District → Ward → Hamlet -->
          <div x-show="geoMode === 'old'" x-cloak class="space-y-3">
            <div>
              <label class="block text-xs text-slate-500 mb-1">Tỉnh (cũ)</label>
              <select x-model="geoSelection.old.province" @change="loadDistrictsOld()" 
                      class="w-full rounded-lg border px-3 py-2">
                <option value="">— Chọn tỉnh —</option>
                <template x-for="province in geoLists.old.provinces" :key="'op'+province.id">
                  <option :value="province.id" x-text="province.name"></option>
                </template>
              </select>
            </div>

            <div>
              <label class="block text-xs text-slate-500 mb-1">Quận/Huyện (cũ)</label>
              <select x-model="geoSelection.old.district" @change="loadWardsOld()" 
                      class="w-full rounded-lg border px-3 py-2" :disabled="!geoLists.old.districts.length">
                <option value="">— Chọn quận/huyện —</option>
                <template x-for="district in geoLists.old.districts" :key="'od'+district.id">
                  <option :value="district.id" x-text="district.name"></option>
                </template>
              </select>
            </div>

            <div>
              <label class="block text-xs text-slate-500 mb-1">Phường/Xã (cũ)</label>
              <select x-model="geoSelection.old.ward" @change="loadHamletsOld()" 
                      class="w-full rounded-lg border px-3 py-2" :disabled="!geoLists.old.wards.length">
                <option value="">— Chọn phường/xã —</option>
                <template x-for="ward in geoLists.old.wards" :key="'ow'+ward.id">
                  <option :value="ward.id" x-text="ward.name"></option>
                </template>
              </select>
            </div>

            <div>
              <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (cũ)</label>
              <select x-model="geoSelection.old.hamlet" 
                      class="w-full rounded-lg border px-3 py-2" :disabled="!geoLists.old.hamlets.length">
                <option value="">— Chọn thôn/ấp —</option>
                <template x-for="hamlet in geoLists.old.hamlets" :key="'oh'+hamlet.id">
                  <option :value="hamlet.id" x-text="hamlet.name"></option>
                </template>
              </select>
            </div>
          </div>

          <!-- NEW Scheme: Province → Ward → Hamlet -->
          <div x-show="geoMode === 'new'" x-cloak class="space-y-3">
            <div>
              <label class="block text-xs text-slate-500 mb-1">Tỉnh (mới)</label>
              <select x-model="geoSelection.new.province" @change="loadWardsNew()" 
                      class="w-full rounded-lg border px-3 py-2">
                <option value="">— Chọn tỉnh —</option>
                <template x-for="province in geoLists.new.provinces" :key="'np'+province.id">
                  <option :value="province.id" x-text="province.name"></option>
                </template>
              </select>
            </div>

            <div>
              <label class="block text-xs text-slate-500 mb-1">Cấp 3 (mới)</label>
              <select x-model="geoSelection.new.ward" @change="loadHamletsNew()" 
                      class="w-full rounded-lg border px-3 py-2" :disabled="!geoLists.new.wards.length">
                <option value="">— Chọn cấp 3 —</option>
                <template x-for="ward in geoLists.new.wards" :key="'nw'+ward.id">
                  <option :value="ward.id" x-text="ward.name"></option>
                </template>
              </select>
            </div>

            <div>
              <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp (mới)</label>
              <select x-model="geoSelection.new.hamlet" 
                      class="w-full rounded-lg border px-3 py-2" :disabled="!geoLists.new.hamlets.length">
                <option value="">— Chọn thôn/ấp —</option>
                <template x-for="hamlet in geoLists.new.hamlets" :key="'nh'+hamlet.id">
                  <option :value="hamlet.id" x-text="hamlet.name"></option>
                </template>
              </select>
            </div>
          </div>

          <!-- Add Button -->
          <button type="button" @click="addSelectedArea()" 
                  class="btn-primary w-full mt-4" :disabled="!canAddArea()">
            <i class="fas fa-plus mr-2"></i>Thêm khu vực đã chọn
          </button>
          
          <!-- Info about selection -->
          <div class="text-xs text-slate-500 mt-2">
            <i class="fas fa-info-circle mr-1"></i>
            <span x-show="geoMode === 'old'">
              Nhấn thêm và nhấn lưu để thêm khu vực tuyển sinh.
            </span>
            <span x-show="geoMode === 'new'">
              Nhấn thêm và nhấn lưu để thêm khu vực tuyển sinh.
            </span>
          </div>
        </div>

        <!-- Selected Areas Preview -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Khu vực sẽ thêm</label>
          <div class="border rounded-lg p-3 bg-slate-50 min-h-[200px]">
            <template x-if="selectedAreasToAdd.length === 0">
              <div class="text-slate-500 text-sm">Chưa chọn khu vực nào</div>
            </template>
            <template x-for="area in selectedAreasToAdd" :key="area.id">
              <div class="flex items-center justify-between py-2 px-3 bg-white rounded border mb-2">
                <div class="text-sm">
                  <div class="font-medium" x-text="area.name"></div>
                  <div class="text-slate-500 text-xs" x-text="area.full_path"></div>
                  <div class="text-slate-400 text-xs font-mono" x-text="area.code"></div>
                  <div class="text-slate-400 text-xs">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                          :class="area.scheme === 'old' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                      <span x-text="area.scheme === 'old' ? 'CŨ' : 'MỚI'"></span>
                    </span>
                  </div>
                </div>
                <button type="button" @click="removeFromSelection(area.id)" 
                        class="text-rose-600 hover:text-rose-800">
                  <i class="fas fa-times"></i>
                </button>
              </div>
            </template>
          </div>
          
          <button type="button" @click="saveSelectedAreas()" 
                  class="btn-primary w-full mt-3" :disabled="selectedAreasToAdd.length === 0">
            <i class="fas fa-save mr-2"></i>Lưu tất cả khu vực
          </button>
        </div>
      </div>
    </div>
  </article>

  <!-- Current Areas List -->
  <article class="card">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold">Khu vực tuyển sinh hiện tại</h2>
      <div class="flex items-center gap-3">
        <div class="text-sm text-slate-500" x-text="`Năm ${selectedYear}`"></div>
        <button type="button" @click="showBulkDelete = !showBulkDelete" 
                class="btn-soft text-sm" :class="showBulkDelete ? 'bg-rose-100 text-rose-700' : ''">
          <i class="fas fa-trash-alt mr-1"></i>
          <span x-text="showBulkDelete ? 'Hủy chọn' : 'Xóa hàng loạt'"></span>
        </button>
      </div>
    </div>

    <!-- Bulk Delete Actions -->
    <div x-show="showBulkDelete && areas.length > 0" x-cloak class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-lg">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" @change="toggleSelectAll()" :checked="selectedAreasToDelete.length === areas.length && areas.length > 0">
            <span>Chọn tất cả</span>
          </label>
          <span class="text-sm text-slate-600" x-text="`Đã chọn: ${selectedAreasToDelete.length}/${areas.length}`"></span>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" @click="clearSelection()" 
                  class="btn-soft text-sm" :disabled="selectedAreasToDelete.length === 0">
            <i class="fas fa-times mr-1"></i>Bỏ chọn
          </button>
          <button type="button" @click="bulkDelete()" 
                  class="btn-danger text-sm" :disabled="selectedAreasToDelete.length === 0">
            <i class="fas fa-trash-alt mr-1"></i>Xóa đã chọn
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" x-cloak class="text-center py-8">
      <div class="inline-flex items-center gap-2 text-slate-600">
        <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        Đang tải...
      </div>
    </div>

    <!-- Areas Table -->
    <div x-show="!loading" class="-mx-4 overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="th text-left" x-show="showBulkDelete">
              <input type="checkbox" @change="toggleSelectAll()" :checked="selectedAreasToDelete.length === areas.length && areas.length > 0">
            </th>
            <th class="th text-left">#</th>
            <th class="th text-left">Tên khu vực</th>
            <th class="th text-left">Cấp</th>
            <th class="th text-left">Mã</th>
            <th class="th text-right">Thao tác</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <template x-for="(area, index) in areas" :key="area.id">
            <tr class="hover:bg-slate-50" :class="selectedAreasToDelete.includes(area.id) ? 'bg-rose-50' : ''">
              <td class="td" x-show="showBulkDelete">
                <input type="checkbox" :value="area.id" @change="toggleAreaSelection(area.id)" 
                       :checked="selectedAreasToDelete.includes(area.id)">
              </td>
              <td class="td" x-text="index + 1"></td>
              <td class="td">
                <div class="font-medium" x-text="area.geo_unit.name"></div>
                <div class="text-xs text-slate-500" x-text="area.geo_unit.code"></div>
              </td>
              <td class="td">
                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                      :class="getLevelClass(area.geo_unit.type_level)">
                  <span x-text="getLevelName(area.geo_unit.type_level)"></span>
                </span>
                <div class="text-xs text-slate-500 mt-1" x-text="area.geo_unit.type?.name || ''"></div>
              </td>
              <td class="td font-mono text-sm" x-text="area.geo_unit.code"></td>
              <td class="td text-right">
                <button type="button" @click="removeArea(area.id)" 
                        class="text-rose-600 hover:text-rose-800">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </template>
          <template x-if="areas.length === 0">
            <tr>
              <td class="td text-center text-slate-500" :colspan="showBulkDelete ? 6 : 5">
                <div class="py-8">
                  <div class="text-slate-400 mb-2">
                    <i class="fas fa-map-marker-alt text-4xl"></i>
                  </div>
                  <div class="text-lg font-medium mb-1">Chưa có khu vực tuyển sinh</div>
                  <div class="text-sm">Năm <span x-text="selectedYear"></span></div>
                  <div class="text-xs text-slate-400 mt-2">
                    Nhấn "Thêm khu vực" ở trên để bắt đầu thêm khu vực tuyển sinh
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </article>
</div>

@endsection

@push('scripts')
<script>
window.schoolAreasManager = function() {
  console.log('schoolAreasManager function called');
  return {
    // Data
    school: @json($school),
    selectedYear: new Date().getFullYear(),
    yearOptions: [],
    areas: [],
    loading: false,
    showAddForm: false,
    
    // Geo mode and selection
    geoMode: 'new', // 'old' or 'new'
    geoLists: {
      old: { provinces: [], districts: [], wards: [], hamlets: [] },
      new: { provinces: [], wards: [], hamlets: [] }
    },
    geoSelection: {
      old: { province: '', district: '', ward: '', hamlet: '' },
      new: { province: '', ward: '', hamlet: '' }
    },
    selectedAreasToAdd: [],
    
    // Bulk delete
    showBulkDelete: false,
    selectedAreasToDelete: [],

    // Methods
    init() {
      // Đảm bảo năm được set đúng ngay từ đầu
      this.selectedYear = new Date().getFullYear();
      console.log('Initializing with selectedYear:', this.selectedYear);
      this.generateYearOptions();
      this.loadProvinces('old');
      this.loadProvinces('new');
      this.loadAreas();
      this.updateSchemeByYear();
    },

    updateSchemeByYear() {
      // Tự động chọn scheme dựa vào năm tuyển sinh
      // Năm 2025 trở về sau: dùng scheme mới
      // Năm 2024 trở về trước: dùng scheme cũ
      const newMode = this.selectedYear >= 2025 ? 'new' : 'old';
      
      // Chỉ thay đổi mode nếu khác với mode hiện tại
      if (this.geoMode !== newMode) {
        this.geoMode = newMode;
        this.onModeChanged();
      }
      
      console.log('Updated scheme by year:', this.selectedYear, 'mode:', this.geoMode);
    },

    generateYearOptions() {
      const currentYear = new Date().getFullYear();
      this.yearOptions = [];
      for (let i = currentYear - 2; i <= currentYear + 3; i++) {
        this.yearOptions.push(i);
      }
      console.log('Generated year options:', this.yearOptions, 'current year:', currentYear);
      
      // Force update select element sau khi options được tạo
      this.$nextTick(() => {
        this.selectedYear = currentYear;
        console.log('Force updated selectedYear to:', this.selectedYear);
        // Trigger change event để load dữ liệu
        this.loadAreas();
      });
    },

    async loadProvinces(scheme) {
      try {
        const response = await fetch(`/admin/geo/provinces?scheme=${scheme}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const provinces = await response.json();
        this.geoLists[scheme].provinces = provinces;
      } catch (error) {
        console.error(`Error loading ${scheme} provinces:`, error);
        this.geoLists[scheme].provinces = [];
      }
    },

    onModeChanged() {
      // Reset selections when changing mode
      if (this.geoMode === 'old') {
        this.geoSelection.new = { province: '', ward: '', hamlet: '' };
        this.geoLists.new.wards = [];
        this.geoLists.new.hamlets = [];
      } else {
        this.geoSelection.old = { province: '', district: '', ward: '', hamlet: '' };
        this.geoLists.old.districts = [];
        this.geoLists.old.wards = [];
        this.geoLists.old.hamlets = [];
      }
    },

    // OLD Scheme methods
    async loadDistrictsOld() {
      if (!this.geoSelection.old.province) {
        this.geoLists.old.districts = [];
        this.geoLists.old.wards = [];
        this.geoLists.old.hamlets = [];
        this.geoSelection.old.district = '';
        this.geoSelection.old.ward = '';
        this.geoSelection.old.hamlet = '';
        return;
      }
      
      try {
        const response = await fetch(`/admin/geo/children/${this.geoSelection.old.province}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const data = await response.json();
        this.geoLists.old.districts = data.children || [];
        this.geoLists.old.wards = [];
        this.geoLists.old.hamlets = [];
        this.geoSelection.old.district = '';
        this.geoSelection.old.ward = '';
        this.geoSelection.old.hamlet = '';
      } catch (error) {
        console.error('Error loading old districts:', error);
        this.geoLists.old.districts = [];
      }
    },

    async loadWardsOld() {
      if (!this.geoSelection.old.district) {
        this.geoLists.old.wards = [];
        this.geoLists.old.hamlets = [];
        this.geoSelection.old.ward = '';
        this.geoSelection.old.hamlet = '';
        return;
      }
      
      try {
        const response = await fetch(`/admin/geo/children/${this.geoSelection.old.district}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const data = await response.json();
        this.geoLists.old.wards = data.children || [];
        this.geoLists.old.hamlets = [];
        this.geoSelection.old.ward = '';
        this.geoSelection.old.hamlet = '';
      } catch (error) {
        console.error('Error loading old wards:', error);
        this.geoLists.old.wards = [];
      }
    },

    async loadHamletsOld() {
      if (!this.geoSelection.old.ward) {
        this.geoLists.old.hamlets = [];
        this.geoSelection.old.hamlet = '';
        return;
      }
      
      try {
        const response = await fetch(`/admin/geo/children/${this.geoSelection.old.ward}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const data = await response.json();
        this.geoLists.old.hamlets = data.children || [];
        this.geoSelection.old.hamlet = '';
      } catch (error) {
        console.error('Error loading old hamlets:', error);
        this.geoLists.old.hamlets = [];
      }
    },

    // NEW Scheme methods
    async loadWardsNew() {
      if (!this.geoSelection.new.province) {
        this.geoLists.new.wards = [];
        this.geoLists.new.hamlets = [];
        this.geoSelection.new.ward = '';
        this.geoSelection.new.hamlet = '';
        return;
      }
      
      try {
        const response = await fetch(`/admin/geo/children/${this.geoSelection.new.province}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const data = await response.json();
        this.geoLists.new.wards = data.children || [];
        this.geoLists.new.hamlets = [];
        this.geoSelection.new.ward = '';
        this.geoSelection.new.hamlet = '';
      } catch (error) {
        console.error('Error loading new wards:', error);
        this.geoLists.new.wards = [];
      }
    },

    async loadHamletsNew() {
      if (!this.geoSelection.new.ward) {
        this.geoLists.new.hamlets = [];
        this.geoSelection.new.hamlet = '';
        return;
      }
      
      try {
        const response = await fetch(`/admin/geo/children/${this.geoSelection.new.ward}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });
        const data = await response.json();
        this.geoLists.new.hamlets = data.children || [];
        this.geoSelection.new.hamlet = '';
      } catch (error) {
        console.error('Error loading new hamlets:', error);
        this.geoLists.new.hamlets = [];
      }
    },

    async loadAreas() {
      this.loading = true;
      console.log('Loading areas for year:', this.selectedYear);
      try {
        const response = await fetch(`/admin/school-areas/${this.school.id}?year=${this.selectedYear}`, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        const data = await response.json();
        console.log('Loaded areas:', data);
        this.areas = data.areas || [];
        // Cập nhật scheme khi thay đổi năm
        this.updateSchemeByYear();
        
        // Reset bulk delete khi thay đổi năm
        this.selectedAreasToDelete = [];
        this.showBulkDelete = false;
        
        // Thông báo nếu không có dữ liệu
        if (this.areas.length === 0) {
          console.log('No areas found for year:', this.selectedYear);
        } else {
          console.log(`Found ${this.areas.length} areas for year ${this.selectedYear}`);
        }
      } catch (error) {
        console.error('Error loading areas:', error);
        this.areas = [];
        alert('Không thể tải danh sách khu vực tuyển sinh. Vui lòng thử lại.');
      } finally {
        this.loading = false;
      }
    },

    canAddArea() {
      if (this.geoMode === 'old') {
        // Cho phép chọn từ tỉnh trở lên (ít nhất phải có tỉnh)
        return this.geoSelection.old.province;
      } else {
        // Cho phép chọn từ tỉnh trở lên (ít nhất phải có tỉnh)
        return this.geoSelection.new.province;
      }
    },

    addSelectedArea() {
      if (!this.canAddArea()) return;

      let selectedGeo = null;
      let fullPath = '';

      if (this.geoMode === 'old') {
        // OLD scheme: Province → District → Ward → Hamlet
        // Ưu tiên chọn cấp thấp nhất có dữ liệu
        if (this.geoSelection.old.hamlet && this.geoLists.old.hamlets.length > 0) {
          selectedGeo = this.geoLists.old.hamlets.find(h => h.id == this.geoSelection.old.hamlet);
        } else if (this.geoSelection.old.ward && this.geoLists.old.wards.length > 0) {
          selectedGeo = this.geoLists.old.wards.find(w => w.id == this.geoSelection.old.ward);
        } else if (this.geoSelection.old.district && this.geoLists.old.districts.length > 0) {
          selectedGeo = this.geoLists.old.districts.find(d => d.id == this.geoSelection.old.district);
        } else if (this.geoSelection.old.province) {
          selectedGeo = this.geoLists.old.provinces.find(p => p.id == this.geoSelection.old.province);
        }

        // Build full path for OLD scheme
        const province = this.geoLists.old.provinces.find(p => p.id == this.geoSelection.old.province);
        const district = this.geoLists.old.districts.find(d => d.id == this.geoSelection.old.district);
        const ward = this.geoLists.old.wards.find(w => w.id == this.geoSelection.old.ward);
        
        fullPath = province?.name || '';
        if (district && selectedGeo?.name !== province?.name) fullPath += ` > ${district.name}`;
        if (ward && selectedGeo?.name !== district?.name) fullPath += ` > ${ward.name}`;
        if (this.geoSelection.old.hamlet && selectedGeo?.name !== ward?.name) {
          fullPath += ` > ${selectedGeo.name}`;
        }

      } else {
        // NEW scheme: Province → Ward → Hamlet
        // Ưu tiên chọn cấp thấp nhất có dữ liệu
        if (this.geoSelection.new.hamlet && this.geoLists.new.hamlets.length > 0) {
          selectedGeo = this.geoLists.new.hamlets.find(h => h.id == this.geoSelection.new.hamlet);
        } else if (this.geoSelection.new.ward && this.geoLists.new.wards.length > 0) {
          selectedGeo = this.geoLists.new.wards.find(w => w.id == this.geoSelection.new.ward);
        } else if (this.geoSelection.new.province) {
          selectedGeo = this.geoLists.new.provinces.find(p => p.id == this.geoSelection.new.province);
        }

        // Build full path for NEW scheme
        const province = this.geoLists.new.provinces.find(p => p.id == this.geoSelection.new.province);
        const ward = this.geoLists.new.wards.find(w => w.id == this.geoSelection.new.ward);
        
        fullPath = province?.name || '';
        if (ward && selectedGeo?.name !== province?.name) fullPath += ` > ${ward.name}`;
        if (this.geoSelection.new.hamlet && selectedGeo?.name !== ward?.name) {
          fullPath += ` > ${selectedGeo.name}`;
        }
      }

      if (selectedGeo && !this.selectedAreasToAdd.find(a => a.id === selectedGeo.id)) {
        this.selectedAreasToAdd.push({
          id: selectedGeo.id,
          name: selectedGeo.name,
          full_path: fullPath,
          type_level: selectedGeo.type_level,
          code: selectedGeo.code,
          scheme: this.geoMode
        });
      }
    },

    removeFromSelection(areaId) {
      this.selectedAreasToAdd = this.selectedAreasToAdd.filter(a => a.id !== areaId);
    },

    async saveSelectedAreas() {
      if (this.selectedAreasToAdd.length === 0) return;

      try {
        const response = await fetch(`/admin/schools/${this.school.id}/areas`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: JSON.stringify({
            year: this.selectedYear,
            geo_ids: this.selectedAreasToAdd.map(a => a.id)
          })
        });

        if (response.ok) {
          this.selectedAreasToAdd = [];
          this.loadAreas();
          this.showAddForm = false;
          // Reset geo selection
          this.geoSelection.old = { province: '', district: '', ward: '', hamlet: '' };
          this.geoSelection.new = { province: '', ward: '', hamlet: '' };
          this.geoLists.old.districts = [];
          this.geoLists.old.wards = [];
          this.geoLists.old.hamlets = [];
          this.geoLists.new.wards = [];
          this.geoLists.new.hamlets = [];
          // Show success message
          alert('Đã lưu khu vực tuyển sinh thành công!');
        } else {
          const errorData = await response.json().catch(() => ({}));
          alert(errorData.message || 'Có lỗi xảy ra khi lưu khu vực tuyển sinh');
        }
      } catch (error) {
        console.error('Error saving areas:', error);
        alert('Có lỗi xảy ra khi lưu khu vực tuyển sinh');
      }
    },

    async removeArea(areaId) {
      if (!confirm('Bạn có chắc chắn muốn xóa khu vực tuyển sinh này?')) return;

      try {
        const response = await fetch(`/admin/schools/${this.school.id}/areas/${areaId}`, {
          method: 'DELETE',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        });

        if (response.ok) {
          this.loadAreas();
          alert('Đã xóa khu vực tuyển sinh thành công!');
        } else {
          const errorData = await response.json().catch(() => ({}));
          alert(errorData.message || 'Có lỗi xảy ra khi xóa khu vực tuyển sinh');
        }
      } catch (error) {
        console.error('Error removing area:', error);
        alert('Có lỗi xảy ra khi xóa khu vực tuyển sinh');
      }
    },

    getLevelName(level) {
      const levels = {
        1: 'Tỉnh',
        2: 'Huyện', 
        3: 'Xã',
        4: 'Thôn'
      };
      return levels[level] || 'Không xác định';
    },

    getLevelClass(level) {
      const classes = {
        1: 'bg-blue-100 text-blue-800',
        2: 'bg-green-100 text-green-800',
        3: 'bg-yellow-100 text-yellow-800',
        4: 'bg-purple-100 text-purple-800'
      };
      return classes[level] || 'bg-gray-100 text-gray-800';
    },

    // Bulk delete methods
    toggleAreaSelection(areaId) {
      const index = this.selectedAreasToDelete.indexOf(areaId);
      if (index > -1) {
        this.selectedAreasToDelete.splice(index, 1);
      } else {
        this.selectedAreasToDelete.push(areaId);
      }
    },

    toggleSelectAll() {
      if (this.selectedAreasToDelete.length === this.areas.length) {
        this.selectedAreasToDelete = [];
      } else {
        this.selectedAreasToDelete = this.areas.map(area => area.id);
      }
    },

    clearSelection() {
      this.selectedAreasToDelete = [];
    },

    async bulkDelete() {
      if (this.selectedAreasToDelete.length === 0) return;

      const count = this.selectedAreasToDelete.length;
      if (!confirm(`Bạn có chắc chắn muốn xóa ${count} khu vực tuyển sinh đã chọn?`)) return;

      try {
        // Xóa từng khu vực một cách tuần tự
        for (const areaId of this.selectedAreasToDelete) {
          const response = await fetch(`/admin/schools/${this.school.id}/areas/${areaId}`, {
            method: 'DELETE',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
          });

          if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
          }
        }

        // Reload danh sách và reset selection
        this.selectedAreasToDelete = [];
        this.showBulkDelete = false;
        this.loadAreas();
        alert(`Đã xóa thành công ${count} khu vực tuyển sinh!`);
      } catch (error) {
        console.error('Error bulk deleting areas:', error);
        alert('Có lỗi xảy ra khi xóa khu vực tuyển sinh. Vui lòng thử lại.');
      }
    }
  }
}
</script>
@endpush
