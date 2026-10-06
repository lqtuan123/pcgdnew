@extends('layouts.admin')

@section('title', 'Tạo người dùng mới')
@section('crumb', 'Tạo người dùng')

@section('content')
  <div class="mb-6">
    <a href="{{ route('admin.users.index') }}" class="btn-soft">← Quay lại</a>
  </div>

  <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
    @csrf
    
    <!-- Thông tin cơ bản -->
    <section class="card">
      <h2 class="text-lg font-semibold mb-4">Thông tin cơ bản</h2>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Tên người dùng *</label>
          <input type="text" class="w-full rounded-lg border px-3 py-2 @error('name') border-rose-300 @enderror" 
                 name="name" value="{{ old('name') }}" required>
          @error('name')
            <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
          <input type="email" class="w-full rounded-lg border px-3 py-2 @error('email') border-rose-300 @enderror" 
                 name="email" value="{{ old('email') }}" required>
          @error('email')
            <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
      </div>
      
      <div class="grid md:grid-cols-2 gap-4 mt-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Số điện thoại</label>
          <input type="text" class="w-full rounded-lg border px-3 py-2 @error('phone_number') border-rose-300 @enderror" 
                 name="phone_number" value="{{ old('phone_number') }}">
          @error('phone_number')
            <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Vai trò *</label>
          <select class="w-full rounded-lg border px-3 py-2 @error('role') border-rose-300 @enderror" 
                  name="role" required>
            <option value="">-- Chọn vai trò --</option>
            @foreach($roles as $role)
              <option value="{{ $role->name }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                {{ $role->display_name }}
              </option>
            @endforeach
          </select>
          @error('role')
            <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>
      </div>
    </section>

    <!-- Phân quyền Role Level -->
    <section class="card">
      <h2 class="text-lg font-semibold mb-4">Phân quyền Role Level</h2>
      
      <div class="space-y-4">
        <!-- Role Level -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Cấp độ Role *</label>
          <select name="role_level" class="w-full rounded-lg border px-3 py-2 @error('role_level') border-rose-300 @enderror" 
                  required onchange="onRoleLevelChange(this.value)">
            <option value="">-- Chọn cấp độ --</option>
            <option value="province" {{ old('role_level') === 'province' ? 'selected' : '' }}>Province - Tỉnh/Thành phố</option>
            <option value="ward" {{ old('role_level') === 'ward' ? 'selected' : '' }}>Ward - Xã/Phường</option>
            <option value="school" {{ old('role_level') === 'school' ? 'selected' : '' }}>School - Trường học</option>
          </select>
          @error('role_level')
            <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>

        <!-- Province selection -->
        <div id="province-section" style="display: none;">
          <label class="block text-sm font-medium text-slate-700 mb-2">Tỉnh/Thành phố *</label>
          <select name="province_id" class="w-full rounded-lg border px-3 py-2 @error('province_id') border-rose-300 @enderror">
            <option value="">-- Chọn tỉnh/thành phố --</option>
            @foreach($provinces as $province)
              <option value="{{ $province->id }}" {{ old('province_id') == $province->id ? 'selected' : '' }}>
                {{ $province->name }}
              </option>
            @endforeach
          </select>
          @error('province_id')
            <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
          @enderror
        </div>

        <!-- Ward selection -->
        <div id="ward-section" style="display: none;">
          <div class="space-y-4">
            <!-- Province selection for ward -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Tỉnh/Thành phố *</label>
              <select id="ward-province-select" name="province_id" class="w-full rounded-lg border px-3 py-2 @error('province_id') border-rose-300 @enderror" onchange="loadWardsByProvince(this.value)">
                <option value="">-- Chọn tỉnh/thành phố --</option>
                @foreach($provinces as $province)
                  <option value="{{ $province->id }}" {{ old('province_id') == $province->id ? 'selected' : '' }}>
                    {{ $province->name }}
                  </option>
                @endforeach
              </select>
              @error('province_id')
                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
              @enderror
            </div>
            
            <!-- Ward selection -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Xã/Phường *</label>
              <select id="ward-select" name="ward_id" class="w-full rounded-lg border px-3 py-2 @error('ward_id') border-rose-300 @enderror">
                <option value="">-- Chọn xã/phường --</option>
              </select>
              @error('ward_id')
                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>

        <!-- School selection -->
        <div id="school-section" style="display: none;">
          <div class="space-y-4">
            <!-- Province selection for school -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Tỉnh/Thành phố *</label>
              <select id="school-province-select" name="province_id" class="w-full rounded-lg border px-3 py-2 @error('province_id') border-rose-300 @enderror" onchange="loadWardsByProvinceForSchool(this.value)">
                <option value="">-- Chọn tỉnh/thành phố --</option>
                @foreach($provinces as $province)
                  <option value="{{ $province->id }}" {{ old('province_id') == $province->id ? 'selected' : '' }}>
                    {{ $province->name }}
                  </option>
                @endforeach
              </select>
              @error('province_id')
                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
              @enderror
            </div>
            
            <!-- Ward selection for school -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Xã/Phường *</label>
              <select id="school-ward-select" name="ward_id" class="w-full rounded-lg border px-3 py-2 @error('ward_id') border-rose-300 @enderror" onchange="loadSchoolsByWard(this.value)">
                <option value="">-- Chọn xã/phường --</option>
              </select>
              @error('ward_id')
                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
              @enderror
            </div>
            
            <!-- School selection -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Trường học *</label>
              <select id="school-select" name="school_id" class="w-full rounded-lg border px-3 py-2 @error('school_id') border-rose-300 @enderror">
                <option value="">-- Chọn trường học --</option>
              </select>
              @error('school_id')
                <div class="text-rose-600 text-xs mt-1">{{ $message }}</div>
              @enderror
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Phân quyền chức năng -->
    <section class="card">
      <h2 class="text-lg font-semibold mb-4">Phân quyền chức năng</h2>
      
      <div class="space-y-4">
        @foreach($categories as $category)
          <div class="border rounded-lg p-4">
            <h3 class="font-medium text-slate-800 mb-3">{{ $category->name }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              @foreach($functions->where('category', $category->code) as $function)
                <div class="border rounded-lg p-3">
                  <div class="font-medium text-sm mb-2">{{ $function->name }}</div>
                  <div class="space-y-2">
                    <label class="flex items-center">
                      <input type="checkbox" name="permissions[{{ $function->code }}][view]" value="1" 
                             class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                      <span class="ml-2 text-sm">Xem</span>
                    </label>
                    <label class="flex items-center">
                      <input type="checkbox" name="permissions[{{ $function->code }}][create]" value="1" 
                             class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                      <span class="ml-2 text-sm">Tạo</span>
                    </label>
                    <label class="flex items-center">
                      <input type="checkbox" name="permissions[{{ $function->code }}][edit]" value="1" 
                             class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                      <span class="ml-2 text-sm">Sửa</span>
                    </label>
                    <label class="flex items-center">
                      <input type="checkbox" name="permissions[{{ $function->code }}][delete]" value="1" 
                             class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                      <span class="ml-2 text-sm">Xóa</span>
                    </label>
                  </div>
                </div>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>
    </section>

    <!-- Submit buttons -->
    <div class="flex gap-3">
      <button type="submit" class="btn-primary">Tạo người dùng</button>
      <a href="{{ route('admin.users.index') }}" class="btn-soft">Hủy</a>
    </div>
  </form>

  <script>
    function onRoleLevelChange(roleLevel) {
      // Hide all sections first
      document.getElementById('province-section').style.display = 'none';
      document.getElementById('ward-section').style.display = 'none';
      document.getElementById('school-section').style.display = 'none';

      // Clear all dependent dropdowns
      document.getElementById('ward-select').innerHTML = '<option value="">-- Chọn xã/phường --</option>';
      document.getElementById('school-ward-select').innerHTML = '<option value="">-- Chọn xã/phường --</option>';
      document.getElementById('school-select').innerHTML = '<option value="">-- Chọn trường học --</option>';

      // Show relevant section based on role level
      switch(roleLevel) {
        case 'province':
          document.getElementById('province-section').style.display = 'block';
          break;
        case 'ward':
          document.getElementById('ward-section').style.display = 'block';
          break;
        case 'school':
          document.getElementById('school-section').style.display = 'block';
          break;
      }
    }

    // Load wards by province for ward role
    function loadWardsByProvince(provinceId) {
      const wardSelect = document.getElementById('ward-select');
      wardSelect.innerHTML = '<option value="">-- Chọn xã/phường --</option>';
      
      if (!provinceId) return;
      
      fetch(`/admin/geo/children/${provinceId}`)
        .then(response => response.json())
        .then(data => {
          data.children.forEach(ward => {
            const option = document.createElement('option');
            option.value = ward.id;
            option.textContent = ward.name;
            wardSelect.appendChild(option);
          });
        })
        .catch(error => console.error('Error loading wards:', error));
    }

    // Load wards by province for school role
    function loadWardsByProvinceForSchool(provinceId) {
      const wardSelect = document.getElementById('school-ward-select');
      const schoolSelect = document.getElementById('school-select');
      
      wardSelect.innerHTML = '<option value="">-- Chọn xã/phường --</option>';
      schoolSelect.innerHTML = '<option value="">-- Chọn trường học --</option>';
      
      if (!provinceId) return;
      
      fetch(`/admin/geo/children/${provinceId}`)
        .then(response => response.json())
        .then(data => {
          data.children.forEach(ward => {
            const option = document.createElement('option');
            option.value = ward.id;
            option.textContent = ward.name;
            wardSelect.appendChild(option);
          });
        })
        .catch(error => console.error('Error loading wards:', error));
    }

    // Load schools by ward
    function loadSchoolsByWard(wardId) {
      const schoolSelect = document.getElementById('school-select');
      schoolSelect.innerHTML = '<option value="">-- Chọn trường học --</option>';
      
      if (!wardId) return;
      
      fetch(`/api/schools/${wardId}`)
        .then(response => response.json())
        .then(data => {
          data.forEach(school => {
            const option = document.createElement('option');
            option.value = school.id;
            option.textContent = school.name;
            schoolSelect.appendChild(option);
          });
        })
        .catch(error => console.error('Error loading schools:', error));
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      const oldRoleLevel = '{{ old('role_level') }}';
      if (oldRoleLevel) {
        onRoleLevelChange(oldRoleLevel);
        
        // Load data if old values exist
        const oldProvinceId = '{{ old('province_id') }}';
        const oldWardId = '{{ old('ward_id') }}';
        
        if (oldRoleLevel === 'ward' && oldProvinceId) {
          loadWardsByProvince(oldProvinceId);
          setTimeout(() => {
            document.getElementById('ward-select').value = oldWardId;
          }, 100);
        } else if (oldRoleLevel === 'school' && oldProvinceId) {
          loadWardsByProvinceForSchool(oldProvinceId);
          if (oldWardId) {
            setTimeout(() => {
              document.getElementById('school-ward-select').value = oldWardId;
              loadSchoolsByWard(oldWardId);
              setTimeout(() => {
                document.getElementById('school-select').value = '{{ old('school_id') }}';
              }, 100);
            }, 100);
          }
        }
      }
    });
  </script>
@endsection
