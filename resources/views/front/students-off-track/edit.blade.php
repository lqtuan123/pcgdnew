@extends('layouts.front')
@section('title','Chỉnh sửa học sinh trái tuyến')
@section('crumb','Chỉnh sửa')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6" x-data="schoolSearch()">
  
  <!-- Header -->
  <div class="card">
    <h1 class="text-lg font-semibold">Chỉnh sửa thông tin học sinh trái tuyến</h1>
    <p class="text-sm text-slate-600 mt-1">
      Chỉnh sửa thông tin học sinh: <b>{{ $student->ho_dem }} {{ $student->ten }}</b>
    </p>
  </div>

  <!-- Form chỉnh sửa -->
  <form method="POST" action="{{ route('front.students-off-track.update', $student->id) }}" class="mt-3">
    @csrf
    @method('PUT')
    
    <!-- Hidden fields để giữ lại bộ lọc -->
    @if(request()->has('academic_year'))
      <input type="hidden" name="academic_year" value="{{ request()->get('academic_year') }}">
    @endif
    @if(request()->has('scheme'))
      <input type="hidden" name="scheme" value="{{ request()->get('scheme') }}">
    @endif
    @if(request()->has('search'))
      <input type="hidden" name="search" value="{{ request()->get('search') }}">
    @endif
    @if(request()->has('geo'))
      @foreach(request()->get('geo', []) as $key => $value)
        <input type="hidden" name="geo[{{ $key }}]" value="{{ $value }}">
      @endforeach
    @endif
    
    <!-- Thông tin cá nhân -->
    <div class="card">
      <h3 class="text-md font-semibold mb-3 text-blue-800">Thông tin cá nhân</h3>
      <div class="grid gap-4 md:grid-cols-12">
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Họ đệm *</label>
          <input type="text" name="ho_dem" value="{{ old('ho_dem', $student->ho_dem) }}" 
                 class="w-full rounded-lg border px-3 py-2 @error('ho_dem') border-red-500 @enderror" required>
          @error('ho_dem')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Tên *</label>
          <input type="text" name="ten" value="{{ old('ten', $student->ten) }}" 
                 class="w-full rounded-lg border px-3 py-2 @error('ten') border-red-500 @enderror" required>
          @error('ten')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Ngày sinh</label>
          <input type="number" name="dob_day" value="{{ old('dob_day', $student->dob_day) }}" 
                 min="1" max="31" class="w-full rounded-lg border px-3 py-2 @error('dob_day') border-red-500 @enderror">
          @error('dob_day')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Tháng sinh</label>
          <input type="number" name="dob_month" value="{{ old('dob_month', $student->dob_month) }}" 
                 min="1" max="12" class="w-full rounded-lg border px-3 py-2 @error('dob_month') border-red-500 @enderror">
          @error('dob_month')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Năm sinh</label>
          <input type="number" name="dob_year" value="{{ old('dob_year', $student->dob_year) }}" 
                 min="1900" max="{{ date('Y') }}" class="w-full rounded-lg border px-3 py-2 @error('dob_year') border-red-500 @enderror">
          @error('dob_year')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Số định danh cá nhân</label>
          <input type="text" name="identity_number" value="{{ old('identity_number', $student->identity_number) }}" 
                 class="w-full rounded-lg border px-3 py-2 @error('identity_number') border-red-500 @enderror">
          @error('identity_number')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Giới tính</label>
          <select name="is_female" class="w-full rounded-lg border px-3 py-2 @error('is_female') border-red-500 @enderror">
            <option value="0" {{ old('is_female', $student->is_female) == 0 ? 'selected' : '' }}>Nam</option>
            <option value="1" {{ old('is_female', $student->is_female) == 1 ? 'selected' : '' }}>Nữ</option>
          </select>
          @error('is_female')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Dân tộc</label>
          <select name="ethnicity_id" class="w-full rounded-lg border px-3 py-2 @error('ethnicity_id') border-red-500 @enderror">
            <option value="">-- Chọn dân tộc --</option>
            @foreach($ethnicities as $ethnicity)
              <option value="{{ $ethnicity->id }}" {{ old('ethnicity_id', $student->ethnicity_id) == $ethnicity->id ? 'selected' : '' }}>
                {{ $ethnicity->name }}
              </option>
            @endforeach
          </select>
          @error('ethnicity_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Tôn giáo</label>
          <select name="religion_id" class="w-full rounded-lg border px-3 py-2 @error('religion_id') border-red-500 @enderror">
            <option value="">-- Chọn tôn giáo --</option>
            @foreach($religions as $religion)
              <option value="{{ $religion->id }}" {{ old('religion_id', $student->religion_id) == $religion->id ? 'selected' : '' }}>
                {{ $religion->name }}
              </option>
            @endforeach
          </select>
          @error('religion_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Diện ưu tiên</label>
          <select name="priority_category_id" class="w-full rounded-lg border px-3 py-2 @error('priority_category_id') border-red-500 @enderror">
            <option value="">-- Chọn diện ưu tiên --</option>
            @foreach($priorityCategories as $priority)
              <option value="{{ $priority->id }}" {{ old('priority_category_id', $student->priority_category_id) == $priority->id ? 'selected' : '' }}>
                {{ $priority->name }}
              </option>
            @endforeach
          </select>
          @error('priority_category_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
      </div>
    </div>

    <!-- Thông tin nơi đang ở -->
    <div class="card mt-3">
      <h3 class="text-md font-semibold mb-3 text-green-800">Thông tin nơi đang ở</h3>
      <div class="grid gap-4 md:grid-cols-12">
        <div class="md:col-span-12">
          <label class="block text-sm font-medium text-gray-700 mb-1">Nơi ở</label>
          @php
            // Chuyển đổi mã địa lý thành ID để truyền cho geo-filter
            $stayProvinceId = null;
            $stayWardId = null;
            
            if ($student->stay_province_code_new) {
              $province = \App\Models\GeoUnit::where('code', $student->stay_province_code_new)->first();
              $stayProvinceId = $province?->id;
            }
            
            if ($student->stay_ward_code_new) {
              $ward = \App\Models\GeoUnit::where('code', $student->stay_ward_code_new)->first();
              $stayWardId = $ward?->id;
            }
            
          @endphp
          <!-- Geo filter đơn giản -->
          <div class="grid md:grid-cols-12 gap-3" id="stay-geo-picker">
            
            <!-- Tỉnh -->
            <div class="md:col-span-6">
              <label class="block text-xs text-slate-500 mb-1">Tỉnh/Thành</label>
              <select class="w-full rounded-lg border px-3 py-2" 
                      name="stay_geo[province_id]" 
                      id="province-select">
                <option value="">— Chọn tỉnh —</option>
              </select>
            </div>
            
            <!-- Xã -->
            <div class="md:col-span-6">
              <label class="block text-xs text-slate-500 mb-1">Xã/Phường</label>
              <select class="w-full rounded-lg border px-3 py-2" 
                      name="stay_geo[ward_id]" 
                      id="ward-select"
                      disabled>
                <option value="">— Chọn xã/phường —</option>
              </select>
            </div>
          </div>
          
          <script>
            document.addEventListener('DOMContentLoaded', async function() {
              const provinceSelect = document.getElementById('province-select');
              const wardSelect = document.getElementById('ward-select');
              
              const initialProvinceId = {{ $stayProvinceId ?? 'null' }};
              const initialWardId = {{ $stayWardId ?? 'null' }};
              
              
              // Load provinces
              try {
                const response = await fetch('/ajax/geo/provinces?scheme=new');
                const provinces = await response.json();
                
                // Populate province select
                provinces.forEach(province => {
                  const option = document.createElement('option');
                  option.value = province.id;
                  option.textContent = province.name;
                  if (province.id == initialProvinceId) {
                    option.selected = true;
                  }
                  provinceSelect.appendChild(option);
                });
                
                // If we have initial province, load wards
                if (initialProvinceId) {
                  await loadWards(initialProvinceId);
                }
                
              } catch (error) {
                console.error('Error loading provinces:', error);
              }
              
              // Province change handler
              provinceSelect.addEventListener('change', async function() {
                const provinceId = this.value;
                
                // Clear wards
                wardSelect.innerHTML = '<option value="">— Chọn xã/phường —</option>';
                wardSelect.disabled = true;
                
                if (provinceId) {
                  await loadWards(provinceId);
                }
              });
              
              async function loadWards(provinceId) {
                try {
                  const response = await fetch(`/ajax/geo/children/${provinceId}`);
                  const data = await response.json();
                  const wards = data.children || [];
                  
                  // Populate ward select
                  wards.forEach(ward => {
                    const option = document.createElement('option');
                    option.value = ward.id;
                    option.textContent = ward.name;
                    if (ward.id == initialWardId) {
                      option.selected = true;
                    }
                    wardSelect.appendChild(option);
                  });
                  
                  wardSelect.disabled = false;
                  
                } catch (error) {
                  console.error('Error loading wards:', error);
                }
              }
            });
          </script>
        </div>
      </div>
    </div>

    <!-- Thông tin học tập -->
    <div class="card mt-3">
      <h3 class="text-md font-semibold mb-3 text-purple-800">Thông tin học tập</h3>
      <div class="grid gap-4 md:grid-cols-12">
        <div class="md:col-span-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Năm học *</label>
          <input type="text" name="academic_year" value="{{ old('academic_year', $student->academic_year) }}" 
                 class="w-full rounded-lg border px-3 py-2 @error('academic_year') border-red-500 @enderror" required>
          @error('academic_year')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Lớp học</label>
          <input type="text" name="class_label" value="{{ old('class_label', $student->class_label) }}" 
                 class="w-full rounded-lg border px-3 py-2 @error('class_label') border-red-500 @enderror">
          @error('class_label')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Trường</label>
          <div class="relative">
            <input type="text" 
                   x-model="schoolSearch" 
                   @input="searchSchools()"
                   @focus="showSchoolDropdown = true"
                   @blur="setTimeout(() => showSchoolDropdown = false, 200)"
                   placeholder="Tìm kiếm trường..."
                   class="w-full rounded-lg border px-3 py-2 @error('school_id') border-red-500 @enderror">
            
            <!-- Hidden input để submit giá trị -->
            <input type="hidden" name="school_id" x-model="selectedSchoolId">
            
            <!-- Dropdown kết quả tìm kiếm -->
            <div x-show="showSchoolDropdown && filteredSchools.length > 0" 
                 x-cloak
                 class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
              <template x-for="school in filteredSchools" :key="school.id">
                <div @click="selectSchool(school)" 
                     class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0">
                  <div class="font-medium" x-text="school.code"></div>
                  <div class="text-sm text-gray-600" x-text="school.name"></div>
                </div>
              </template>
            </div>
            
            <!-- Hiển thị trường đã chọn -->
            <div x-show="selectedSchool" class="mt-2 p-2 bg-blue-50 rounded border">
              <div class="text-sm">
                <span class="font-medium" x-text="selectedSchool?.code"></span> - 
                <span x-text="selectedSchool?.name"></span>
              </div>
              <button type="button" @click="clearSchool()" class="text-xs text-red-600 hover:text-red-800">
                Xóa
              </button>
            </div>
          </div>
          @error('school_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Khối học</label>
          <select name="education_block_id" class="w-full rounded-lg border px-3 py-2 @error('education_block_id') border-red-500 @enderror">
            <option value="">-- Chọn khối học --</option>
            @foreach($educationBlocks as $block)
              <option value="{{ $block->id }}" {{ old('education_block_id', $student->education_block_id) == $block->id ? 'selected' : '' }}>
                {{ $block->name }}
              </option>
            @endforeach
          </select>
          @error('education_block_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Bậc tốt nghiệp</label>
          <select name="graduation_level_id" class="w-full rounded-lg border px-3 py-2 @error('graduation_level_id') border-red-500 @enderror">
            <option value="">-- Chọn bậc tốt nghiệp --</option>
            @foreach($graduationLevels as $level)
              <option value="{{ $level->id }}" {{ old('graduation_level_id', $student->graduation_level_id) == $level->id ? 'selected' : '' }}>
                {{ $level->name }}
              </option>
            @endforeach
          </select>
          @error('graduation_level_id')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Học 2 buổi/ngày</label>
          <select name="hoc_hai_buoi" class="w-full rounded-lg border px-3 py-2 @error('hoc_hai_buoi') border-red-500 @enderror">
            <option value="0" {{ old('hoc_hai_buoi', $student->hoc_hai_buoi) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('hoc_hai_buoi', $student->hoc_hai_buoi) == 1 ? 'selected' : '' }}>Có</option>
          </select>
          @error('hoc_hai_buoi')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Học đầy đủ theo quy định</label>
          <select name="hoc_9_10_buoi_tuan" class="w-full rounded-lg border px-3 py-2 @error('hoc_9_10_buoi_tuan') border-red-500 @enderror">
            <option value="0" {{ old('hoc_9_10_buoi_tuan', $student->hoc_9_10_buoi_tuan) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('hoc_9_10_buoi_tuan', $student->hoc_9_10_buoi_tuan) == 1 ? 'selected' : '' }}>Có</option>
          </select>
          @error('hoc_9_10_buoi_tuan')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Bổ túc</label>
          <select name="is_bo_tuc" class="w-full rounded-lg border px-3 py-2 @error('is_bo_tuc') border-red-500 @enderror">
            <option value="0" {{ old('is_bo_tuc', $student->is_bo_tuc) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('is_bo_tuc', $student->is_bo_tuc) == 1 ? 'selected' : '' }}>Có</option>
          </select>
          @error('is_bo_tuc')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-3">
          <label class="block text-sm font-medium text-gray-700 mb-1">Năm tốt nghiệp</label>
          <input type="number" name="graduation_year" value="{{ old('graduation_year', $student->graduation_year) }}" 
                 min="1900" max="{{ date('Y') + 10 }}" class="w-full rounded-lg border px-3 py-2 @error('graduation_year') border-red-500 @enderror">
          @error('graduation_year')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Bỏ học lớp</label>
          <select name="bo_hoc_lop" class="w-full rounded-lg border px-3 py-2 @error('bo_hoc_lop') border-red-500 @enderror">
            <option value="">-- Chọn lớp bỏ học --</option>
            @foreach($educationBlocks as $block)
              <option value="{{ $block->name }}" {{ old('bo_hoc_lop', $student->bo_hoc_lop) == $block->name ? 'selected' : '' }}>
                {{ $block->name }}
              </option>
            @endforeach
          </select>
          @error('bo_hoc_lop')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Bỏ học năm</label>
          <input type="number" name="bo_hoc_nam" value="{{ old('bo_hoc_nam', $student->bo_hoc_nam) }}" 
                 class="w-full rounded-lg border px-3 py-2 @error('bo_hoc_nam') border-red-500 @enderror">
          @error('bo_hoc_nam')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
      </div>
    </div>

    <!-- Thông tin khuyết tật -->
    <div class="card mt-3">
      <h3 class="text-md font-semibold mb-3 text-orange-800">Thông tin khuyết tật</h3>
      <div class="grid gap-4 md:grid-cols-12">
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật vận động</label>
          <select name="disability_van_dong" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_van_dong', $student->disability_van_dong) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_van_dong', $student->disability_van_dong) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật nghe, nói</label>
          <select name="disability_nghe_noi" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_nghe_noi', $student->disability_nghe_noi) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_nghe_noi', $student->disability_nghe_noi) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật nhìn</label>
          <select name="disability_nhin" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_nhin', $student->disability_nhin) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_nhin', $student->disability_nhin) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật thần kinh, tâm thần</label>
          <select name="disability_than_kinh" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_than_kinh', $student->disability_than_kinh) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_than_kinh', $student->disability_than_kinh) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật trí tuệ</label>
          <select name="disability_tri_tue" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_tri_tue', $student->disability_tri_tue) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_tri_tue', $student->disability_tri_tue) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật học tập</label>
          <select name="disability_hoc_tap" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_hoc_tap', $student->disability_hoc_tap) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_hoc_tap', $student->disability_hoc_tap) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Tự kỷ</label>
          <select name="disability_tu_ky" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_tu_ky', $student->disability_tu_ky) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_tu_ky', $student->disability_tu_ky) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật khác</label>
          <select name="disability_khac" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_khac', $student->disability_khac) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_khac', $student->disability_khac) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Có chứng nhận khuyết tật</label>
          <select name="disability_has_certificate" class="w-full rounded-lg border px-3 py-2">
            <option value="0" {{ old('disability_has_certificate', $student->disability_has_certificate) == 0 ? 'selected' : '' }}>Không</option>
            <option value="1" {{ old('disability_has_certificate', $student->disability_has_certificate) == 1 ? 'selected' : '' }}>Có</option>
          </select>
        </div>
        
          <div class="md:col-span-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật khả năng học tập</label>
            <select name="disability_can_learn" class="w-full rounded-lg border px-3 py-2 @error('disability_can_learn') border-red-500 @enderror">
              <option value="0" {{ old('disability_can_learn', $student->disability_can_learn) == 0 ? 'selected' : '' }}>Không</option>
              <option value="1" {{ old('disability_can_learn', $student->disability_can_learn) == 1 ? 'selected' : '' }}>Có</option>
            </select>
            @error('disability_can_learn')
              <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
          </div>
      </div>
    </div>

    <!-- Thông tin hoàn cảnh -->
    <div class="card mt-3">
      <h3 class="text-md font-semibold mb-3 text-pink-800">Thông tin hoàn cảnh</h3>
      <div class="grid gap-4 md:grid-cols-12">
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Hoàn cảnh đặc biệt</label>
          <select name="special_circumstance_name" class="w-full rounded-lg border px-3 py-2 @error('special_circumstance_name') border-red-500 @enderror">
            <option value="">-- Chọn hoàn cảnh đặc biệt --</option>
            @foreach($specialCircumstances as $circumstance)
              <option value="{{ $circumstance->name }}" {{ old('special_circumstance_name', $student->special_circumstance_name) == $circumstance->name ? 'selected' : '' }}>
                {{ $circumstance->name }}
              </option>
            @endforeach
          </select>
          @error('special_circumstance_name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        
        <div class="md:col-span-6">
          <label class="block text-sm font-medium text-gray-700 mb-1">Chi tiết hoàn cảnh đặc biệt</label>
          <input type="text" name="special_circumstance_detail" value="{{ old('special_circumstance_detail', $student->special_circumstance_detail) }}" 
                 class="w-full rounded-lg border px-3 py-2 @error('special_circumstance_detail') border-red-500 @enderror">
          @error('special_circumstance_detail')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
      </div>
    </div>

    <!-- Nút thao tác -->
    <div class="card mt-3">
      <div class="flex gap-3 justify-end">
        <a href="{{ route('front.students-off-track.index', request()->query()) }}" 
           class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
          Hủy
        </a>
        <button type="submit" 
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          Cập nhật
        </button>
      </div>
    </div>
  </form>
</div>

<script>
function schoolSearch() {
  return {
    // School search
    schoolSearch: '',
    showSchoolDropdown: false,
    filteredSchools: [],
    selectedSchool: null,
    selectedSchoolId: '{{ old("school_id", $student->school_id) }}',
    allSchools: @json($schools),
    
    
    init() {
      // Khởi tạo trường đã chọn nếu có
      if (this.selectedSchoolId) {
        const school = this.allSchools.find(s => s.id == this.selectedSchoolId);
        if (school) {
          this.selectedSchool = school;
          this.schoolSearch = school.code + ' - ' + school.name;
        }
      }
      
    },
    
    // School search methods
    searchSchools() {
      if (this.schoolSearch.length < 2) {
        this.filteredSchools = [];
        return;
      }
      
      const searchTerm = this.schoolSearch.toLowerCase();
      this.filteredSchools = this.allSchools.filter(school => 
        school.code.toLowerCase().includes(searchTerm) || 
        school.name.toLowerCase().includes(searchTerm)
      ).slice(0, 10); // Giới hạn 10 kết quả
    },
    
    selectSchool(school) {
      this.selectedSchool = school;
      this.selectedSchoolId = school.id;
      this.schoolSearch = school.code + ' - ' + school.name;
      this.showSchoolDropdown = false;
    },
    
    clearSchool() {
      this.selectedSchool = null;
      this.selectedSchoolId = '';
      this.schoolSearch = '';
      this.filteredSchools = [];
    },
    
  };
}

</script>
@endsection
