{{-- resources/views/partials/front/user-geo-filter.blade.php --}}
@php
  $namePrefix = $namePrefix ?? 'geo';
  $schemeInit = $scheme ?? request('scheme','new');
  $showHamlet = $showHamlet ?? true;
  $showSchool = $showSchool ?? true;
  
  // Lấy thông tin user hiện tại
  $currentUser = auth()->user();
  $userRoleInfo = $currentUser ? $currentUser->getRoleLevelAndScope() : null;
  
  // Xác định các trường bị khóa dựa trên role level của user
  $lockedFields = [];
  if ($currentUser) {
    switch ($currentUser->role_level) {
      case 'province':
        $lockedFields = ['province_id'];
        break;
      case 'ward':
        $lockedFields = ['province_id', 'ward_id'];
        break;
      case 'school':
        $lockedFields = ['province_id', 'ward_id', 'school_id'];
        break;
    }
  }

  // Xử lý ward_id dựa trên scheme
  $userWardId = $currentUser->ward_id ?? null;
  $userProvinceId = $currentUser->province_id ?? null;
  $userWardCode = null;
  
  // Nếu user có ward_id, kiểm tra scheme của ward đó và lấy ward_code
  if ($userWardId) {
    $userWard = \App\Models\GeoUnit::find($userWardId);
    if ($userWard && $userWard->scheme === $schemeInit) {
      $userWardCode = $userWard->code;
    } else {
      // Nếu scheme không khớp, không sử dụng ward_id
      $userWardId = null;
    }
  }

  $vals = [
    'province_id' => (string)($provinceId ?? request($namePrefix.'.province_id') ?? $userProvinceId ?? ''),
    'district_id' => (string)($districtId ?? request($namePrefix.'.district_id')),
    'ward_id'     => (string)($wardId ?? request($namePrefix.'.ward_id') ?? $userWardId ?? ''),
    'village_id'  => (string)($villageId ?? request($namePrefix.'.village_id')),
    'school_id'   => (string)($schoolId ?? request($namePrefix.'.school_id') ?? $currentUser->school_id ?? ''),
    'ward_code'   => $userWardCode, // Thêm ward_code để JavaScript sử dụng
  ];
  $wrapperClass = $wrapperClass ?? '';
@endphp

<div class="{{ $wrapperClass }}"
     x-data="userGeoPicker({ 
       scheme: @js($schemeInit), 
       value: @js($vals), 
       showHamlet: @js($showHamlet),
       showSchool: @js($showSchool),
       lockedFields: @js($lockedFields),
       userRoleInfo: @js($userRoleInfo),
       userWardCode: @js($userWardCode)
     })"
     x-init="init()">
  
  {{-- Hiển thị thông tin phạm vi của user --}}
  @if($userRoleInfo && $userRoleInfo['scope_display'])
    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
      <div class="flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-600"></i>
        <span class="text-sm font-medium text-blue-800">Phạm vi quản lý:</span>
        <span class="text-sm text-blue-700">{{ $userRoleInfo['scope_display'] }}</span>
      </div>
    </div>
  @endif


  {{-- Chọn sơ đồ địa bàn --}}
  <div class="mb-4">
    <label class="block text-xs text-slate-500 mb-2">Sơ đồ địa bàn</label>
    <div class="flex items-center gap-4">
      <label class="inline-flex items-center gap-2">
        <input type="radio" name="scheme" value="old" class="accent-cyan-600" x-model="scheme"> Cũ (Tỉnh → Huyện → Xã)
      </label>
      <label class="inline-flex items-center gap-2">
        <input type="radio" name="scheme" value="new" class="accent-cyan-600" x-model="scheme"> Mới (Tỉnh → Xã)
      </label>
    </div>
  </div>

  {{-- OLD SCHEME --}}
  <template x-if="scheme==='old'">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3" x-cloak>
      {{-- Năm học --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Năm học</label>
        <select class="w-full rounded-lg border px-2 py-1 text-sm"
                name="{{ $namePrefix }}[school_year]">
          <option value="">Chọn năm học</option>
          <option value="2023-2024">2023-2024</option>
          <option value="2024-2025">2024-2025</option>
          <option value="2025-2026">2025-2026</option>
        </select>
      </div>

      {{-- Tỉnh --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">
          Tỉnh/TP
          <span x-show="lockedFields.includes('province_id')" class="text-red-500">*</span>
        </label>
        <div class="relative">
          <select class="w-full rounded-lg border px-2 py-1 text-sm"
                  :class="{ 'bg-gray-100 cursor-not-allowed': lockedFields.includes('province_id') }"
                  name="{{ $namePrefix }}[province_id]"
                  x-model="selProvOld" @change="onChangeProvOld" x-ref="provOld"
                  :disabled="lockedFields.includes('province_id')">
            <option value="">Chọn tỉnh</option>
            <template x-for="p in provincesOld" :key="'op'+p.id">
              <option :value="String(p.id)"
                      :selected="String(p.id)===selProvOld"
                      x-text="p.name"></option>
            </template>
          </select>
          <div x-show="loading.provinces" class="absolute right-2 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
          </div>
        </div>
      </div>

      {{-- Huyện --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Quận/Huyện</label>
        <select class="w-full rounded-lg border px-2 py-1 text-sm"
                name="{{ $namePrefix }}[district_id]"
                x-model="selDistOld" @change="onChangeDistOld"
                :disabled="!districtsOld.length" x-ref="distOld">
          <option value="">Chọn quận/huyện</option>
          <template x-for="d in districtsOld" :key="'od'+d.id">
            <option :value="String(d.id)"
                    :selected="String(d.id)===selDistOld"
                    x-text="d.name"></option>
          </template>
        </select>
      </div>

      {{-- Xã/Phường --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">
          Xã/Phường
          <span x-show="lockedFields.includes('ward_id')" class="text-red-500">*</span>
        </label>
        <div class="relative">
          <select class="w-full rounded-lg border px-2 py-1 text-sm"
                  :class="{ 'bg-gray-100 cursor-not-allowed': lockedFields.includes('ward_id') }"
                  name="{{ $namePrefix }}[ward_id]"
                  x-model="selWardOld" @change="onChangeWardOld"
                  :disabled="!wardsOld.length || lockedFields.includes('ward_id')" x-ref="wardOld">
            <option value="">Chọn xã/phường</option>
            <template x-for="w in wardsOld" :key="'ow'+w.id">
              <option :value="String(w.id)"
                      :selected="String(w.id)===selWardOld"
                      x-text="w.name"></option>
            </template>
          </select>
          <div x-show="loading.wards" class="absolute right-2 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
          </div>
        </div>
      </div>

      {{-- Thôn/Ấp --}}
      @if ($showHamlet)
      <div>
        <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp</label>
        <div class="relative">
          <select class="w-full rounded-lg border px-2 py-1 text-sm"
                  name="{{ $namePrefix }}[village_id]"
                  x-model="selHamletOld" @change="onChangeHamletOld"
                  :disabled="!hamletsOld.length" x-ref="hamletOld">
            <option value="">Chọn thôn/ấp</option>
            <template x-for="h in hamletsOld" :key="'oh'+h.id">
              <option :value="String(h.id)"
                      :selected="String(h.id)===selHamletOld"
                      x-text="h.name"></option>
            </template>
          </select>
          <div x-show="loading.hamlets" class="absolute right-2 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
          </div>
        </div>
      </div>
      @endif

      {{-- Trường học --}}
      @if ($showSchool)
      <div>
        <label class="block text-xs text-slate-500 mb-1">
          Trường học
          <span x-show="lockedFields.includes('school_id')" class="text-red-500">*</span>
        </label>
        <div class="relative">
          <select class="w-full rounded-lg border px-2 py-1 text-sm"
                  :class="{ 'bg-gray-100 cursor-not-allowed': lockedFields.includes('school_id') }"
                  name="{{ $namePrefix }}[school_id]"
                  x-model="selSchoolOld" @change="onChangeSchoolOld"
                  :disabled="lockedFields.includes('school_id')" x-ref="schoolOld">
            <option value="">Chọn trường</option>
            <template x-for="s in schoolsOld" :key="'os'+s.id">
              <option :value="String(s.id)"
                      :selected="String(s.id)===selSchoolOld"
                      x-text="s.name"></option>
            </template>
          </select>
          <div x-show="loading.schools" class="absolute right-2 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
          </div>
        </div>
      </div>
      @endif
    </div>
  </template>

  {{-- NEW SCHEME --}}
  <template x-if="scheme==='new'">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3" x-cloak>
      {{-- Năm học --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">Năm học</label>
        <select class="w-full rounded-lg border px-2 py-1 text-sm"
                name="{{ $namePrefix }}[school_year]">
          <option value="">Chọn năm học</option>
          <option value="2023-2024">2023-2024</option>
          <option value="2024-2025">2024-2025</option>
          <option value="2025-2026">2025-2026</option>
        </select>
      </div>

      {{-- Tỉnh --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">
          Tỉnh/TP
          <span x-show="lockedFields.includes('province_id')" class="text-red-500">*</span>
        </label>
        <div class="relative">
          <select class="w-full rounded-lg border px-2 py-1 text-sm"
                  :class="{ 'bg-gray-100 cursor-not-allowed': lockedFields.includes('province_id') }"
                  name="{{ $namePrefix }}[province_id]"
                  x-model="selProvNew" @change="onChangeProvNew" x-ref="provNew"
                  :disabled="lockedFields.includes('province_id')">
            <option value="">Chọn tỉnh</option>
            <template x-for="p in provincesNew" :key="'np'+p.id">
              <option :value="String(p.id)"
                      :selected="String(p.id)===selProvNew"
                      x-text="p.name"></option>
            </template>
          </select>
          <div x-show="loading.provinces" class="absolute right-2 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
          </div>
        </div>
      </div>

      {{-- Xã/Phường --}}
      <div>
        <label class="block text-xs text-slate-500 mb-1">
          Xã/Phường
          <span x-show="lockedFields.includes('ward_id')" class="text-red-500">*</span>
        </label>
        <div class="relative">
          <select class="w-full rounded-lg border px-2 py-1 text-sm"
                  :class="{ 'bg-gray-100 cursor-not-allowed': lockedFields.includes('ward_id') }"
                  name="{{ $namePrefix }}[ward_id]"
                  x-model="selWardNew" @change="onChangeWardNew"
                  :disabled="!wardsNew.length || lockedFields.includes('ward_id')" x-ref="wardNew">
            <option value="">Chọn xã/phường</option>
            <template x-for="w in wardsNew" :key="'nw'+w.id">
              <option :value="String(w.id)"
                      :selected="String(w.id)===selWardNew"
                      x-text="w.name"></option>
            </template>
          </select>
          <div x-show="loading.wards" class="absolute right-2 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
          </div>
        </div>
      </div>

      {{-- Thôn/Ấp --}}
      @if ($showHamlet)
      <div>
        <label class="block text-xs text-slate-500 mb-1">Thôn/Ấp</label>
        <div class="relative">
          <select class="w-full rounded-lg border px-2 py-1 text-sm"
                  name="{{ $namePrefix }}[village_id]"
                  x-model="selHamletNew" @change="onChangeHamletNew"
                  :disabled="!hamletsNew.length" x-ref="hamletNew">
            <option value="">Chọn thôn/ấp</option>
            <template x-for="h in hamletsNew" :key="'nh'+h.id">
              <option :value="String(h.id)"
                      :selected="String(h.id)===selHamletNew"
                      x-text="h.name"></option>
            </template>
          </select>
          <div x-show="loading.hamlets" class="absolute right-2 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
          </div>
        </div>
      </div>
      @endif

      {{-- Trường học --}}
      @if ($showSchool)
      <div>
        <label class="block text-xs text-slate-500 mb-1">
          Trường học
          <span x-show="lockedFields.includes('school_id')" class="text-red-500">*</span>
        </label>
        <div class="relative">
          <select class="w-full rounded-lg border px-2 py-1 text-sm"
                  :class="{ 'bg-gray-100 cursor-not-allowed': lockedFields.includes('school_id') }"
                  name="{{ $namePrefix }}[school_id]"
                  x-model="selSchoolNew" @change="onChangeSchoolNew"
                  :disabled="lockedFields.includes('school_id')" x-ref="schoolNew">
            <option value="">Chọn trường</option>
            <template x-for="s in schoolsNew" :key="'ns'+s.id">
              <option :value="String(s.id)"
                      :selected="String(s.id)===selSchoolNew"
                      x-text="s.name"></option>
            </template>
          </select>
          <div x-show="loading.schools" class="absolute right-2 top-1/2 transform -translate-y-1/2">
            <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
          </div>
        </div>
      </div>
      @endif
    </div>
  </template>

</div>

@once
@push('scripts')
<script>
  const GEO_BASE = '{{ url('/ajax/geo') }}';
  const SCHOOL_BASE = '{{ url('/ajax/schools') }}';
  const GEO_API  = {
    provinces: `${GEO_BASE}/provinces`,
    children : (id) => `${GEO_BASE}/children/${id}`,
    schools: (wardId) => `${SCHOOL_BASE}/by-ward/${wardId}`,
    schoolsByWardCode: (wardCode) => `${SCHOOL_BASE}/by-ward-code/${wardCode}`,
    schoolById: (schoolId) => `${SCHOOL_BASE}/by-id/${schoolId}`,
    schoolsByProvince: (provinceId) => `${SCHOOL_BASE}/by-province/${provinceId}`,
    allSchools: `${SCHOOL_BASE}/all`,
    // API từ create.blade.php
    apiSchools: (wardId) => `/api/schools/${wardId}`,
  };

  function userGeoPicker(init = {}){
    return {
      // state
      scheme: init.scheme || 'new',
      showSchool: !!init.showSchool,
      lockedFields: init.lockedFields || [],
      userRoleInfo: init.userRoleInfo || null,
      
      // Loading states
      loading: {
        provinces: false,
        districts: false,
        wards: false,
        hamlets: false,
        schools: false
      },
      
      // Debounce timers
      debounceTimers: {},

      // OLD SCHEME
      provincesOld:[], districtsOld:[], wardsOld:[], hamletsOld:[], schoolsOld:[],
      // NEW SCHEME  
      provincesNew:[], wardsNew:[], hamletsNew:[], schoolsNew:[],

      // OLD SCHEME SELECTIONS
      selProvOld:   String(init.value?.province_id ?? '') || '',
      selDistOld:   String(init.value?.district_id ?? '') || '',
      selWardOld:   String(init.value?.ward_id     ?? '') || '',
      selHamletOld: String(init.value?.village_id  ?? '') || '',
      selSchoolOld: String(init.value?.school_id   ?? '') || '',

      // NEW SCHEME SELECTIONS
      selProvNew:   String(init.value?.province_id ?? '') || '',
      selWardNew:   String(init.value?.ward_id     ?? '') || '',
      selHamletNew: String(init.value?.village_id  ?? '') || '',
      selSchoolNew: String(init.value?.school_id   ?? '') || '',

      async init(){
        try {
          // Load provinces cho cả 2 scheme
          await Promise.all([this.loadProvinces('old'), this.loadProvinces('new')]);

          // Nếu user bị khóa theo school, load trường theo hierarchy như bình thường
          if (this.lockedFields.includes('school_id') && this.showSchool) {
            // OLD SCHEME - Load schools theo hierarchy
            if (this.selWardOld) {
              this.schoolsOld = await this.fetchSchoolsByWardId(this.selWardOld);
            } else if (this.selProvOld) {
              this.schoolsOld = await this.fetchSchoolsByProvince(this.selProvOld);
            } else {
              this.schoolsOld = await this.fetchAllSchools();
            }
            
            // NEW SCHEME - Load schools theo hierarchy
            if (this.selWardNew) {
              this.schoolsNew = await this.fetchSchoolsByWardId(this.selWardNew);
            } else if (this.selProvNew) {
              this.schoolsNew = await this.fetchSchoolsByProvince(this.selProvNew);
            } else {
              this.schoolsNew = await this.fetchAllSchools();
            }
          }

          // Prefill theo query và user data cho OLD SCHEME
          if (this.selProvOld) {
            this.districtsOld = await this.fetchChildren(this.selProvOld);
            if (this.selDistOld) {
              this.wardsOld = await this.fetchChildren(this.selDistOld);
              if (this.selWardOld) {
                if (this.showHamlet) {
                  this.hamletsOld = await this.fetchChildren(this.selWardOld);
                }
                if (this.showSchool && !this.lockedFields.includes('school_id')) {
                  this.schoolsOld = await this.fetchSchoolsByWardId(this.selWardOld);
                }
              }
            }
          }

          // Prefill theo query và user data cho NEW SCHEME
          if (this.selProvNew) {
            this.wardsNew = await this.fetchChildren(this.selProvNew);
            if (this.selWardNew) {
              if (this.showHamlet) {
                this.hamletsNew = await this.fetchChildren(this.selWardNew);
              }
              if (this.showSchool && !this.lockedFields.includes('school_id')) {
                this.schoolsNew = await this.fetchSchoolsByWardId(this.selWardNew);
              }
            }
          }

          // Load trường theo hierarchy cho cả 2 scheme (bất kể có bị khóa school_id hay không)
          if (this.showSchool) {
            // OLD SCHEME
            if (this.selWardOld) {
              this.schoolsOld = await this.fetchSchoolsByWardId(this.selWardOld);
            } else if (this.selProvOld) {
              this.schoolsOld = await this.fetchSchoolsByProvince(this.selProvOld);
            } else {
              this.schoolsOld = await this.fetchAllSchools();
            }
            
            // NEW SCHEME
            if (this.selWardNew) {
              this.schoolsNew = await this.fetchSchoolsByWardId(this.selWardNew);
            } else if (this.selProvNew) {
              this.schoolsNew = await this.fetchSchoolsByProvince(this.selProvNew);
            } else {
              this.schoolsNew = await this.fetchAllSchools();
            }
          }
        } catch (error) {
          console.error('Error during initialization:', error);
        }

        // Force re-sync sau khi options render
        this.$nextTick(() => this.refreshSelected());

        // Đổi scheme thì reset bên kia
        this.$watch('scheme', async (to) => {
          if (to==='old'){
            this.selProvNew=this.selWardNew=this.selHamletNew=this.selSchoolNew='';
            this.wardsNew=this.hamletsNew=this.schoolsNew=[];
            // Load trường cho scheme old
            if (this.showSchool) {
              if (this.selWardOld) {
                this.schoolsOld = await this.fetchSchoolsByWardId(this.selWardOld);
              } else if (this.selProvOld) {
                this.schoolsOld = await this.fetchSchoolsByProvince(this.selProvOld);
              } else {
                this.schoolsOld = await this.fetchAllSchools();
              }
            }
          } else {
            this.selProvOld=this.selDistOld=this.selWardOld=this.selHamletOld=this.selSchoolOld='';
            this.districtsOld=this.wardsOld=this.hamletsOld=this.schoolsOld=[];
            // Load trường cho scheme new
            if (this.showSchool) {
              if (this.selWardNew) {
                this.schoolsNew = await this.fetchSchoolsByWardId(this.selWardNew);
              } else if (this.selProvNew) {
                this.schoolsNew = await this.fetchSchoolsByProvince(this.selProvNew);
              } else {
                this.schoolsNew = await this.fetchAllSchools();
              }
            }
          }
          this.$nextTick(() => this.refreshSelected());
        });
      },

      refreshSelected(){
        // đồng bộ giá trị cho chắc ăn
        if (this.$refs.provOld)  this.$refs.provOld.value  = this.selProvOld;
        if (this.$refs.distOld)  this.$refs.distOld.value  = this.selDistOld;
        if (this.$refs.wardOld)  this.$refs.wardOld.value  = this.selWardOld;
        if (this.$refs.hamletOld)this.$refs.hamletOld.value= this.selHamletOld;
        if (this.$refs.schoolOld)this.$refs.schoolOld.value= this.selSchoolOld;

        if (this.$refs.provNew)  this.$refs.provNew.value  = this.selProvNew;
        if (this.$refs.wardNew)  this.$refs.wardNew.value  = this.selWardNew;
        if (this.$refs.hamletNew)this.$refs.hamletNew.value= this.selHamletNew;
        if (this.$refs.schoolNew)this.$refs.schoolNew.value= this.selSchoolNew;
      },

      // Debounce function
      debounce(func, delay, key) {
        if (this.debounceTimers[key]) {
          clearTimeout(this.debounceTimers[key]);
        }
        this.debounceTimers[key] = setTimeout(func, delay);
      },

      async loadProvinces(scheme){
        this.loading.provinces = true;
        try{
          const r = await fetch(`${GEO_API.provinces}?scheme=${scheme}`, {
            headers: {Accept:'application/json'},
            timeout: 10000
          });
          if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
          const items = await r.json();
          if (scheme==='old') this.provincesOld = items; else this.provincesNew = items;
        }catch(e){ 
          console.error('loadProvinces', e);
          if (scheme==='old') this.provincesOld = []; else this.provincesNew = [];
        } finally {
          this.loading.provinces = false;
        }
      },
      
      async fetchChildren(pid){
        if (!pid) return [];
        this.loading.wards = true;
        try{
          const r = await fetch(GEO_API.children(pid), {
            headers: {Accept:'application/json'},
            timeout: 10000
          });
          if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
          const j = await r.json();
          return Array.isArray(j.children) ? j.children : [];
        }catch(e){ 
          console.error('fetchChildren', e); 
          return []; 
        } finally {
          this.loading.wards = false;
        }
      },

      async fetchSchools(wardId){
        if (!wardId || !this.showSchool) return [];
        try{
          const r = await fetch(GEO_API.schools(wardId), {headers:{Accept:'application/json'}});
          const j = await r.json();
          return Array.isArray(j.schools) ? j.schools : [];
        }catch(e){ console.error('fetchSchools', e); return []; }
      },

      async fetchSchoolById(schoolId){
        if (!schoolId || !this.showSchool) return [];
        try{
          const r = await fetch(GEO_API.schoolById(schoolId), {headers:{Accept:'application/json'}});
          const j = await r.json();
          return Array.isArray(j.schools) ? j.schools : [];
        }catch(e){ console.error('fetchSchoolById', e); return []; }
      },

      async fetchAllSchools(){
        if (!this.showSchool) return [];
        try{
          const r = await fetch(GEO_API.allSchools, {headers:{Accept:'application/json'}});
          const j = await r.json();
          return Array.isArray(j.schools) ? j.schools : [];
        }catch(e){ console.error('fetchAllSchools', e); return []; }
      },

      async fetchSchoolsByProvince(provinceId){
        if (!provinceId || !this.showSchool) return [];
        try{
          const r = await fetch(GEO_API.schoolsByProvince(provinceId), {headers:{Accept:'application/json'}});
          const j = await r.json();
          return Array.isArray(j.schools) ? j.schools : [];
        }catch(e){ console.error('fetchSchoolsByProvince', e); return []; }
      },

      async fetchSchoolsByWardId(wardId){
        if (!wardId || !this.showSchool) return [];
        this.loading.schools = true;
        try{
          // Sử dụng API giống như trong create.blade.php
          const r = await fetch(GEO_API.apiSchools(wardId), {
            headers: {Accept:'application/json'},
            timeout: 10000
          });
          if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
          const j = await r.json();
          return Array.isArray(j) ? j : [];
        }catch(e){ 
          console.error('fetchSchoolsByWardId', e); 
          return []; 
        } finally {
          this.loading.schools = false;
        }
      },

      async fetchSchoolsByWardCode(wardCode){
        if (!wardCode || !this.showSchool) return [];
        try{
          const r = await fetch(GEO_API.schoolsByWardCode(wardCode), {headers:{Accept:'application/json'}});
          const j = await r.json();
          return Array.isArray(j.schools) ? j.schools : [];
        }catch(e){ console.error('fetchSchoolsByWardCode', e); return []; }
      },

      // OLD SCHEME HANDLERS
      onChangeProvOld(e){
        this.selProvOld = e.target.value || '';
        this.selDistOld = this.selWardOld = this.selSchoolOld = '';
        this.districtsOld = this.wardsOld = this.schoolsOld = [];
        
        this.debounce(async () => {
          try {
            if (this.selProvOld) {
              this.districtsOld = await this.fetchChildren(this.selProvOld);
            } else {
              this.districtsOld = [];
            }
            
            // Load trường theo province
            if (this.showSchool) {
              if (this.selProvOld) {
                this.schoolsOld = await this.fetchSchoolsByProvince(this.selProvOld);
              } else {
                this.schoolsOld = await this.fetchAllSchools();
              }
            }
            
            // Tự động submit form khi chọn tỉnh
            if (this.selProvOld) {
              this.submitForm();
            }
          } catch (error) {
            console.error('Error in onChangeProvOld:', error);
          } finally {
            this.$nextTick(() => this.refreshSelected());
          }
        }, 300, 'provOld');
      },
      
      async onChangeDistOld(e){
        this.selDistOld = e.target.value || '';
        this.selWardOld = this.selSchoolOld = '';
        this.wardsOld = await this.fetchChildren(this.selDistOld);
        this.schoolsOld = [];
        // Load trường theo province
        if (this.showSchool) {
          if (this.selProvOld) {
            this.schoolsOld = await this.fetchSchoolsByProvince(this.selProvOld);
          } else {
            this.schoolsOld = await this.fetchAllSchools();
          }
        }
        this.$nextTick(() => this.refreshSelected());
      },
      
      onChangeWardOld(e){
        this.selWardOld = e.target.value || '';
        this.selHamletOld = this.selSchoolOld = '';
        
        this.debounce(async () => {
          try {
            // Load thôn/ấp
            if (this.showHamlet) {
              this.hamletsOld = await this.fetchChildren(this.selWardOld);
            }
            
            // Load trường theo ward, province hoặc tất cả trường
            if (this.showSchool) {
              if (this.selWardOld) {
                this.schoolsOld = await this.fetchSchoolsByWardId(this.selWardOld);
              } else if (this.selProvOld) {
                this.schoolsOld = await this.fetchSchoolsByProvince(this.selProvOld);
              } else {
                this.schoolsOld = await this.fetchAllSchools();
              }
            }
            
            // Tự động submit form khi chọn xã
            if (this.selWardOld) {
              this.submitForm();
            }
          } catch (error) {
            console.error('Error in onChangeWardOld:', error);
          } finally {
            this.$nextTick(() => this.refreshSelected());
          }
        }, 300, 'wardOld');
      },
      
      onChangeHamletOld(e){ this.selHamletOld = e.target.value || ''; },
      onChangeSchoolOld(e){ 
        this.selSchoolOld = e.target.value || '';
        // Tự động submit form khi chọn trường
        if (this.selSchoolOld) {
          this.submitForm();
        }
      },

      // NEW SCHEME HANDLERS
      onChangeProvNew(e){
        this.selProvNew = e.target.value || '';
        this.selWardNew = this.selHamletNew = this.selSchoolNew = '';
        this.wardsNew = this.hamletsNew = this.schoolsNew = [];
        
        this.debounce(async () => {
          try {
            if (this.selProvNew) {
              this.wardsNew = await this.fetchChildren(this.selProvNew);
            } else {
              this.wardsNew = [];
            }
            
            // Load trường theo province
            if (this.showSchool) {
              if (this.selProvNew) {
                this.schoolsNew = await this.fetchSchoolsByProvince(this.selProvNew);
              } else {
                this.schoolsNew = await this.fetchAllSchools();
              }
            }
            
            // Tự động submit form khi chọn tỉnh
            if (this.selProvNew) {
              this.submitForm();
            }
          } catch (error) {
            console.error('Error in onChangeProvNew:', error);
          } finally {
            this.$nextTick(() => this.refreshSelected());
          }
        }, 300, 'provNew');
      },
      
      onChangeWardNew(e){
        this.selWardNew = e.target.value || '';
        this.selHamletNew = this.selSchoolNew = '';
        
        this.debounce(async () => {
          try {
            // Load thôn/ấp
            if (this.showHamlet) {
              this.hamletsNew = await this.fetchChildren(this.selWardNew);
            }
            
            // Load trường theo ward, province hoặc tất cả trường
            if (this.showSchool) {
              if (this.selWardNew) {
                this.schoolsNew = await this.fetchSchoolsByWardId(this.selWardNew);
              } else if (this.selProvNew) {
                this.schoolsNew = await this.fetchSchoolsByProvince(this.selProvNew);
              } else {
                this.schoolsNew = await this.fetchAllSchools();
              }
            }
            
            // Tự động submit form khi chọn xã
            if (this.selWardNew) {
              this.submitForm();
            }
          } catch (error) {
            console.error('Error in onChangeWardNew:', error);
          } finally {
            this.$nextTick(() => this.refreshSelected());
          }
        }, 300, 'wardNew');
      },
      
      onChangeHamletNew(e){ this.selHamletNew = e.target.value || ''; },
      onChangeSchoolNew(e){ 
        this.selSchoolNew = e.target.value || '';
        // Tự động submit form khi chọn trường
        if (this.selSchoolNew) {
          this.submitForm();
        }
      },

      // Tự động submit form
      submitForm() {
        // Tìm form chứa geo filter
        const form = this.$el.closest('form');
        if (form) {
          form.submit();
        }
      },
    }
  }
</script>
@endpush
@endonce
