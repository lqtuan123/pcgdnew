{{-- resources/views/components/report-geo-filter.blade.php --}}
@props([
    'namePrefix' => 'geo',
    'showSchool' => true,
    'showAcademicYear' => true,
    'academicYear' => null,
    'wardId' => null,
    'schoolId' => null,
    'reportType' => 'all', // secondary, primary, kindergarten, all
    'wrapperClass' => ''
])

@php
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

  $vals = [
    'ward_id' => (string)($wardId ?? request($namePrefix.'.ward_id') ?? $currentUser->ward_id ?? ''),
    'school_id' => (string)($schoolId ?? request($namePrefix.'.school_id') ?? $currentUser->school_id ?? ''),
    'academic_year' => $academicYear ?? request($namePrefix.'.academic_year') ?? '',
  ];
@endphp

<div class="{{ $wrapperClass }}"
     x-data="reportGeoFilter({ 
       value: @js($vals), 
       showSchool: @js($showSchool),
       showAcademicYear: @js($showAcademicYear),
       lockedFields: @js($lockedFields),
       userRoleInfo: @js($userRoleInfo),
       reportType: @js($reportType)
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

  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    {{-- Năm học --}}
    @if ($showAcademicYear)
    <div>
      <label class="block text-xs text-slate-500 mb-1">
        Năm học
        <span class="text-red-500">*</span>
      </label>
      <div class="relative">
        <select class="w-full rounded-lg border px-2 py-1 text-sm"
                name="{{ $namePrefix }}[academic_year]"
                x-model="academicYear" @change="onAcademicYearChange" x-ref="academicYear">
          <option value="">Chọn năm học</option>
          <template x-for="year in availableYears" :key="year">
            <option :value="year" :selected="year === academicYear" x-text="year"></option>
          </template>
        </select>
        <div x-show="loading.years" class="absolute right-2 top-1/2 transform -translate-y-1/2">
          <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
        </div>
      </div>
    </div>
    @endif

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
                x-model="provinceId" @change="onProvinceChange" x-ref="province"
                :disabled="lockedFields.includes('province_id')">
          <option value="">Chọn tỉnh</option>
          <template x-for="p in provinces" :key="'p'+p.id">
            <option :value="String(p.id)"
                    :selected="String(p.id)===provinceId"
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
                x-model="wardId" @change="onWardChange"
                :disabled="!wards.length || lockedFields.includes('ward_id')" x-ref="ward">
          <option value="">Chọn xã/phường</option>
          <template x-for="w in wards" :key="'w'+w.id">
            <option :value="String(w.id)"
                    :selected="String(w.id)===wardId"
                    x-text="w.name"></option>
          </template>
        </select>
        <div x-show="loading.wards" class="absolute right-2 top-1/2 transform -translate-y-1/2">
          <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
        </div>
      </div>
    </div>

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
                x-model="schoolId" @change="onSchoolChange"
                :disabled="lockedFields.includes('school_id')" x-ref="school">
          <option value="">Chọn trường</option>
          <template x-for="s in schools" :key="'s'+s.id">
            <option :value="String(s.id)"
                    :selected="String(s.id)===schoolId"
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

  {{-- Nút tìm kiếm --}}
  <div class="mt-4 flex justify-end">
    <button type="button" 
            @click="searchReports"
            :disabled="loading.search"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
      <i class="fas fa-search mr-2"></i>
      <span x-show="!loading.search">Tìm kiếm</span>
      <span x-show="loading.search">Đang tìm...</span>
    </button>
  </div>
</div>

@once
@push('scripts')
<script>
  const REPORT_API_BASE = '{{ url('/api/report-aggregation') }}';
  const GEO_API_BASE = '{{ url('/ajax/geo') }}';
  const SCHOOL_API_BASE = '{{ url('/ajax/schools') }}';

  function reportGeoFilter(init = {}) {
    return {
      // State
      academicYear: init.value?.academic_year || '',
      provinceId: '',
      wardId: init.value?.ward_id || '',
      schoolId: init.value?.school_id || '',
      reportType: init.reportType || 'all',
      
      // Data
      availableYears: [],
      provinces: [],
      wards: [],
      schools: [],
      
      // Loading states
      loading: {
        years: false,
        provinces: false,
        wards: false,
        schools: false,
        search: false
      },
      
      // Locked fields
      lockedFields: init.lockedFields || [],
      userRoleInfo: init.userRoleInfo || null,

      async init() {
        try {
          // Load available academic years
          if (init.showAcademicYear) {
            await this.loadAcademicYears();
          }
          
          // Load provinces
          await this.loadProvinces();
          
          // Load wards if province is locked
          if (this.userRoleInfo?.province_id) {
            this.provinceId = String(this.userRoleInfo.province_id);
            await this.loadWards();
          }
          
          // Load schools if ward is locked
          if (this.userRoleInfo?.ward_id) {
            this.wardId = String(this.userRoleInfo.ward_id);
            await this.loadSchools();
          }
          
          // Load schools if school is locked
          if (this.userRoleInfo?.school_id) {
            this.schoolId = String(this.userRoleInfo.school_id);
          }
        } catch (error) {
          console.error('Error during initialization:', error);
        }
      },

      async loadAcademicYears() {
        this.loading.years = true;
        try {
          const response = await fetch(`${REPORT_API_BASE}/available-academic-years`, {
            headers: { 'Accept': 'application/json' }
          });
          const data = await response.json();
          this.availableYears = data.success ? data.data : [];
        } catch (error) {
          console.error('Error loading academic years:', error);
          this.availableYears = [];
        } finally {
          this.loading.years = false;
        }
      },

      async loadProvinces() {
        this.loading.provinces = true;
        try {
          const response = await fetch(`${GEO_API_BASE}/provinces?scheme=new`, {
            headers: { 'Accept': 'application/json' }
          });
          const data = await response.json();
          this.provinces = Array.isArray(data) ? data : [];
        } catch (error) {
          console.error('Error loading provinces:', error);
          this.provinces = [];
        } finally {
          this.loading.provinces = false;
        }
      },

      async loadWards() {
        if (!this.provinceId) {
          this.wards = [];
          return;
        }
        
        this.loading.wards = true;
        try {
          const response = await fetch(`${GEO_API_BASE}/children/${this.provinceId}`, {
            headers: { 'Accept': 'application/json' }
          });
          const data = await response.json();
          this.wards = data.children || [];
        } catch (error) {
          console.error('Error loading wards:', error);
          this.wards = [];
        } finally {
          this.loading.wards = false;
        }
      },

      async loadSchools() {
        if (!this.wardId) {
          this.schools = [];
          return;
        }
        
        this.loading.schools = true;
        try {
          const response = await fetch(`${SCHOOL_API_BASE}/by-ward/${this.wardId}`, {
            headers: { 'Accept': 'application/json' }
          });
          const data = await response.json();
          this.schools = data.schools || [];
        } catch (error) {
          console.error('Error loading schools:', error);
          this.schools = [];
        } finally {
          this.loading.schools = false;
        }
      },

      onAcademicYearChange() {
        // Trigger search if needed
      },

      onProvinceChange() {
        this.wardId = '';
        this.schoolId = '';
        this.wards = [];
        this.schools = [];
        this.loadWards();
      },

      onWardChange() {
        this.schoolId = '';
        this.schools = [];
        this.loadSchools();
      },

      onSchoolChange() {
        // Trigger search if needed
      },

      async searchReports() {
        this.loading.search = true;
        try {
          const params = new URLSearchParams({
            ward_id: this.wardId,
            academic_year: this.academicYear,
            report_type: this.reportType
          });

          const response = await fetch(`${REPORT_API_BASE}/ward-statistics?${params}`, {
            headers: { 'Accept': 'application/json' }
          });
          
          const data = await response.json();
          
          if (data.success) {
            // Emit event with search results
            this.$dispatch('report-search-results', data.data);
          } else {
            console.error('Search error:', data.message);
            this.$dispatch('report-search-error', data.message);
          }
        } catch (error) {
          console.error('Search error:', error);
          this.$dispatch('report-search-error', 'Lỗi khi tìm kiếm báo cáo');
        } finally {
          this.loading.search = false;
        }
      }
    }
  }
</script>
@endpush
@endonce



















