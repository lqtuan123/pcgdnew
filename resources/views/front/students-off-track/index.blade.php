@extends('layouts.front')
@section('title','Danh sách học sinh trái tuyến')

@section('content')
<div x-data="studentManagement()" class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between py-3">
    <h1 class="text-xl font-semibold">Danh sách học sinh trái tuyến</h1>
    <div class="flex gap-2">
      @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'view'))
        <a href="{{ route('front.students-off-track.export-template', array_merge(request()->query(), ['academic_year' => $academicYear])) }}" 
           class="rounded-lg bg-yellow-600 px-3 py-2 text-white hover:bg-yellow-700">
          Xuất file mẫu
        </a>
      @endif
      @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'create'))
        <a href="{{ route('front.students-off-track.import') }}" 
           class="rounded-lg bg-slate-600 px-3 py-2 text-white hover:bg-slate-700">
          Nhập Excel
        </a>
        <button @click="showCreateModal = true" 
                class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700">
          Thêm mới
        </button>
      @endif
      @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'view'))
        <a href="{{ route('front.students-off-track.export', array_merge(request()->query(), ['academic_year' => $academicYear])) }}" 
           class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">
          Xuất Excel
        </a>
      @endif
    </div>
  </div>

  <!-- Form tìm kiếm -->
  <form method="get" class="mb-6 bg-white p-4 rounded-lg shadow">
    <div class="grid gap-4 md:grid-cols-12">
      <!-- Năm học -->
      <div class="md:col-span-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">Năm học</label>
        <select name="academic_year" class="w-full rounded-lg border border-gray-300 px-3 py-2">
          @php
            $currentYear = date('Y');
            $years = [];
            // Tạo danh sách 5 năm: 3 năm cũ + năm hiện tại + 1 năm tiếp theo
            for ($i = -3; $i <= 1; $i++) {
              $year = $currentYear + $i;
              $years[] = $year . '-' . ($year + 1);
            }
            // Sắp xếp từ mới đến cũ
            rsort($years);
          @endphp
          @foreach($years as $year)
            <option value="{{ $year }}" {{ $year == $academicYear ? 'selected' : '' }}>
              {{ $year }}
            </option>
          @endforeach
        </select>
      </div>
      
      <!-- Tìm kiếm -->
      <div class="md:col-span-3">
        <label class="block text-sm font-medium text-gray-700 mb-1">Tìm kiếm</label>
        <input type="text" name="search" value="{{ $search }}" 
               placeholder="Tên, họ đệm, số định danh..." 
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
      </div>
      
      <!-- Nút tìm kiếm -->
      <div class="md:col-span-3 flex items-end">
        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
          Tìm kiếm
        </button>
      </div>
      
      <!-- Nút xóa bộ lọc -->
      <div class="md:col-span-3 flex items-end">
        <a href="{{ route('front.students-off-track.index') }}" 
           class="w-full bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 text-center">
          Xóa bộ lọc
        </a>
      </div>
    </div>
    
    <!-- Bộ chọn địa chính -->
    <div class="mt-4">
      @include('partials.front.geo-filter', [
        'namePrefix' => 'geo',
        'scheme' => request('scheme', 'old'),
        'provinceId' => request('geo.province_id'),
        'districtId' => request('geo.district_id'),
        'wardId' => request('geo.ward_id'),
        'villageId' => request('geo.village_id'),
        'showHamlet' => true,
        'wrapperClass' => 'grid md:grid-cols-12 gap-3'
      ])
    </div>
  </form>

  <!-- Nút xóa hàng loạt và Bảng danh sách -->
  <div x-data="bulkSelect()">
    <!-- Nút xóa hàng loạt -->
    <div class="mb-6 flex gap-3">
      @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'delete'))
        <!-- Xóa các học sinh đã chọn (trang hiện tại) -->
        <form x-ref="bulkSelForm" id="bulkSelForm" method="POST" action="{{ route('front.students-off-track.bulk-delete') }}">
          @csrf
          <button type="button"
                  class="rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                  :disabled="selectedCount===0"
                  x-text="selectedCount>0 ? `Xóa các học sinh đã chọn (${selectedCount})` : 'Xóa các học sinh đã chọn'"
                  @click="confirmBulkDelete()">
          </button>
        </form>
      @endif

      @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'delete'))
        <!-- Xóa hết theo bộ lọc (tất cả trang) -->
        <form method="POST" action="{{ route('front.students-off-track.bulk-delete') }}"
              class="hidden"
              data-confirm="Xóa MỀM TẤT CẢ các học sinh ĐANG LỌC?"
              data-title="Xóa theo bộ lọc" data-ok="Xóa" data-cancel="Hủy">
          @csrf
          <input type="hidden" name="scope" value="filtered">
          <input type="hidden" name="academic_year" value="{{ $academicYear }}">
          <input type="hidden" name="search" value="{{ $search }}">
          <input type="hidden" name="scheme" value="{{ $scheme }}">
          
          <!-- Giữ geo filter -->
          <input type="hidden" name="geo[province_id]" value="{{ request('geo.province_id') }}">
          <input type="hidden" name="geo[district_id]" value="{{ request('geo.district_id') }}">
          <input type="hidden" name="geo[ward_id]" value="{{ request('geo.ward_id') }}">
          <input type="hidden" name="geo[village_id]" value="{{ request('geo.village_id') }}">
          
          <button class="rounded-lg bg-red-500 px-4 py-2 text-white hover:bg-red-600">
            Xóa hết (theo bộ lọc)
          </button>
        </form>
      @endif
    </div>

    <!-- Bảng danh sách -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
    @if($students->count() > 0)
      <div class="overflow-x-auto max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
        <table class="w-full border-collapse text-sm">
          <thead class="sticky top-0 bg-white z-10">
          <!-- Dòng tiêu đề nhóm -->
          <tr class="text-xs font-semibold">
            <th colspan="2" class="th bg-gray-50 text-gray-800 border-b-2 border-gray-200 text-center"></th>
            <th colspan="11" class="th bg-blue-50 text-blue-800 border-b-2 border-blue-200 text-center">Thông tin đối tượng</th>
            <th colspan="2" class="th bg-green-50 text-green-800 border-b-2 border-green-200 text-center">Thông tin nơi đang ở</th>
            <th colspan="10" class="th bg-purple-50 text-purple-800 border-b-2 border-purple-200 text-center">Thông tin học tập</th>
            <th colspan="10" class="th bg-orange-50 text-orange-800 border-b-2 border-orange-200 text-center">Khuyết tật</th>
            <th colspan="2" class="th bg-pink-50 text-pink-800 border-b-2 border-pink-200 text-center">Hoàn cảnh</th>
          </tr>
            <!-- Dòng tiêu đề cột -->
            <tr class="bg-slate-100">
              <th class="th sticky left-0 bg-slate-100 z-20 w-10 border-r border-gray-200">
                <input type="checkbox" @change="toggleAll($event)" aria-label="Chọn tất cả">
              </th>
              <th class="th sticky left-10 bg-slate-100 z-20 w-16 border-r border-gray-200"></th>
              <th class="th">STT</th>
              <th class="th">Họ đệm</th>
              <th class="th">Tên</th>
              <th class="th">Ngày</th>
              <th class="th">Tháng</th>
              <th class="th">Năm sinh</th>
              <th class="th">Số định danh cá nhân</th>
              <th class="th">Nữ</th>
              <th class="th">Dân tộc</th>
              <th class="th">Tôn giáo</th>
              <th class="th">Diện ưu tiên</th>
              <th class="th">Tỉnh/Thành</th>
              <th class="th">Phường/Xã</th>
              <th class="th">Khối học</th>
              <th class="th">Lớp học</th>
              <th class="th">Học 2 buổi/ngày</th>
              <th class="th">Học đầy đủ theo quy định</th>
              <th class="th">Mã trường</th>
              <th class="th">Bậc tốt nghiệp</th>
              <th class="th">Bổ túc</th>
              <th class="th">Năm tốt nghiệp</th>
              <th class="th">Bỏ học lớp</th>
              <th class="th">Bỏ học năm</th>
              <th class="th">Khuyết tật vận động</th>
              <th class="th">Khuyết tật nghe, nói</th>
              <th class="th">Khuyết tật nhìn</th>
              <th class="th">Khuyết tật thần kinh, tâm thần</th>
              <th class="th">Khuyết tật trí tuệ</th>
              <th class="th">Khuyết tật học tập</th>
              <th class="th">Tự kỷ</th>
              <th class="th">Khuyết tật khác</th>
              <th class="th">Có chứng nhận khuyết tật</th>
              <th class="th">Khả năng học tập</th>
              <th class="th">Hoàn cảnh đặc biệt</th>
              <th class="th">Chi tiết hoàn cảnh đặc biệt</th>
            </tr>
          </thead>
          <tbody>
            @foreach($students as $index => $student)
              <tr class="hover:bg-gray-50">
                <td class="td sticky left-0 bg-white z-10 w-10 border-r border-gray-200">
                  <input type="checkbox" class="row-checkbox" name="ids[]" value="{{ $student->id }}"
                        @change="onRowToggle()" aria-label="Chọn học sinh {{ $student->ten }}" form="bulkSelForm">
                </td>
                <td class="td sticky left-10 bg-white z-10 w-16 border-r border-gray-200">
                  @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_trai_tuyen', 'edit'))
                    <a href="{{ route('front.students-off-track.edit', $student->id) }}" 
                       class="text-blue-600 hover:text-blue-800 text-xs px-1 py-0.5 rounded hover:bg-blue-50">
                      Sửa
                    </a>
                  @endif
                </td>
                <td class="td">{{ $students->firstItem() + $index }}</td>
                <td class="td">{{ $student->ho_dem }}</td>
                <td class="td">{{ $student->ten }}</td>
                <td class="td">{{ $student->dob_day }}</td>
                <td class="td">{{ $student->dob_month }}</td>
                <td class="td">{{ $student->dob_year }}</td>
                <td class="td">{{ $student->identity_number }}</td>
                <td class="td">{{ $student->is_female ? 'x' : '' }}</td>
                <td class="td">{{ $student->ethnicity?->name }}</td>
                <td class="td">{{ $student->religion?->name }}</td>
                <td class="td">{{ $student->priorityCategory?->name }}</td>
                <td class="td">{{ $student->getStayProvinceName('new') }}</td>
                <td class="td">{{ $student->getStayWardName('new') }}</td>
                <td class="td">{{ $student->educationBlock?->name }}</td>
                <td class="td">{{ $student->class_label }}</td>
                <td class="td">{{ $student->hoc_hai_buoi ? 'v' : '' }}</td>
                <td class="td">{{ $student->hoc_9_10_buoi_tuan ? 'v' : '' }}</td>
                <td class="td">{{ $student->school?->code }}</td>
                <td class="td">{{ $student->graduationLevel?->name }}</td>
                <td class="td">{{ $student->is_bo_tuc ? 'x' : '' }}</td>
                <td class="td">{{ $student->graduation_year }}</td>
                <td class="td">{{ $student->bo_hoc_lop }}</td>
                <td class="td">{{ $student->bo_hoc_nam }}</td>
                <td class="td">{{ $student->disability_van_dong ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_nghe_noi ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_nhin ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_than_kinh ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_tri_tue ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_hoc_tap ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_tu_ky ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_khac ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_has_certificate ? 'x' : '' }}</td>
                <td class="td">{{ $student->disability_can_learn ? 'x' : '' }}</td>
                <td class="td">{{ $student->special_circumstance_name }}</td>
                <td class="td">{{ $student->special_circumstance_detail }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="px-6 py-3 border-t border-gray-200">
        {{ $students->appends(request()->query())->links() }}
      </div>
    @else
      <div class="text-center py-12">
        <div class="text-gray-500 text-lg mb-4">Chưa có dữ liệu học sinh trái tuyến</div>
      </div>
    @endif
    </div>
  </div>

  <!-- Modal thêm mới học sinh -->
  <div x-show="showCreateModal" 
       x-cloak
       class="fixed inset-0 z-50 overflow-y-auto"
       style="display: none;">
    <div class="flex min-h-screen items-center justify-center p-4">
      <!-- Backdrop -->
      <div class="fixed inset-0 bg-black bg-opacity-50" @click="showCreateModal = false"></div>
      
      <!-- Modal content -->
      <div class="relative bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <!-- Header -->
        <div class="flex items-center justify-between p-6 border-b">
          <h3 class="text-lg font-semibold">Thêm mới học sinh trái tuyến</h3>
          <button @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
        
        <!-- Form content -->
        <form @submit.prevent="createStudent" class="p-6">
          <!-- Thông tin cá nhân -->
          <div class="mb-6">
            <h4 class="text-md font-semibold mb-3 text-blue-800">Thông tin cá nhân</h4>
            <div class="grid gap-4 md:grid-cols-12">
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Họ đệm *</label>
                <input type="text" x-model="newStudent.ho_dem" 
                       class="w-full rounded-lg border px-3 py-2" required>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tên *</label>
                <input type="text" x-model="newStudent.ten" 
                       class="w-full rounded-lg border px-3 py-2" required>
              </div>
              
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ngày sinh</label>
                <input type="number" x-model="newStudent.dob_day" 
                       min="1" max="31" class="w-full rounded-lg border px-3 py-2">
              </div>
              
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tháng sinh</label>
                <input type="number" x-model="newStudent.dob_month" 
                       min="1" max="12" class="w-full rounded-lg border px-3 py-2">
              </div>
              
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Năm sinh</label>
                <input type="number" x-model="newStudent.dob_year" 
                       min="1900" max="{{ date('Y') }}" class="w-full rounded-lg border px-3 py-2">
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Số định danh cá nhân</label>
                <input type="text" x-model="newStudent.identity_number" 
                       class="w-full rounded-lg border px-3 py-2">
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Giới tính</label>
                <select x-model="newStudent.is_female" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Nam</option>
                  <option value="1">Nữ</option>
                </select>
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Dân tộc</label>
                <select x-model="newStudent.ethnicity_id" class="w-full rounded-lg border px-3 py-2">
                  <option value="">-- Chọn dân tộc --</option>
                  @foreach(\App\Models\Ethnicity::all() as $ethnicity)
                    <option value="{{ $ethnicity->id }}">{{ $ethnicity->name }}</option>
                  @endforeach
                </select>
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tôn giáo</label>
                <select x-model="newStudent.religion_id" class="w-full rounded-lg border px-3 py-2">
                  <option value="">-- Chọn tôn giáo --</option>
                  @foreach(\App\Models\Religion::all() as $religion)
                    <option value="{{ $religion->id }}">{{ $religion->name }}</option>
                  @endforeach
                </select>
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Diện ưu tiên</label>
                <select x-model="newStudent.priority_category_id" class="w-full rounded-lg border px-3 py-2">
                  <option value="">-- Chọn diện ưu tiên --</option>
                  @foreach(\App\Models\PriorityCategory::all() as $priority)
                    <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>

          <!-- Thông tin nơi đang ở -->
          <div class="mb-6">
            <h4 class="text-md font-semibold mb-3 text-green-800">Thông tin nơi đang ở</h4>
            <div class="grid gap-4 md:grid-cols-12">
              <div class="md:col-span-12">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nơi ở</label>
                @include('partials.front.geo-filter', [
                  'namePrefix' => 'stay_geo',
                  'scheme' => 'new',
                  'schemeMode' => 'new',      // Khóa ở hành chính mới
                  'showHamlet' => false,
                  'showSchool' => false,      // Bỏ trường trường học
                  'wrapperClass' => 'grid md:grid-cols-12 gap-3',
                  'lockProvince' => false,    // Không khóa tỉnh theo user
                  'lockWard' => false,        // Không khóa xã theo user
                  'lockVillage' => false,      // Không khóa thôn/xóm
                  'lockSchool' => false,      // Không khóa trường
                  'readOnlyNewGeo' => false,  // Không dùng readonly mode
                ])
              </div>
            </div>
          </div>

          <!-- Thông tin học tập -->
          <div class="mb-6">
            <h4 class="text-md font-semibold mb-3 text-purple-800">Thông tin học tập</h4>
            <div class="grid gap-4 md:grid-cols-12">
              <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Năm học *</label>
                <input type="text" x-model="newStudent.academic_year" 
                       class="w-full rounded-lg border px-3 py-2" required>
              </div>
              
              <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Lớp học</label>
                <input type="text" x-model="newStudent.class_label" 
                       class="w-full rounded-lg border px-3 py-2">
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
                         class="w-full rounded-lg border px-3 py-2">
                  
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
              </div>
              
              <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khối học</label>
                <select x-model="newStudent.education_block_id" class="w-full rounded-lg border px-3 py-2">
                  <option value="">-- Chọn khối học --</option>
                  @foreach(\App\Models\EducationBlock::all() as $block)
                    <option value="{{ $block->id }}">{{ $block->name }}</option>
                  @endforeach
                </select>
              </div>
              
              <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Bậc tốt nghiệp</label>
                <select x-model="newStudent.graduation_level_id" class="w-full rounded-lg border px-3 py-2">
                  <option value="">-- Chọn bậc tốt nghiệp --</option>
                  @foreach(\App\Models\GraduationLevel::all() as $level)
                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                  @endforeach
                </select>
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Học 2 buổi/ngày</label>
                <select x-model="newStudent.hoc_hai_buoi" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Học đầy đủ theo quy định</label>
                <select x-model="newStudent.hoc_9_10_buoi_tuan" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Bổ túc</label>
                <select x-model="newStudent.is_bo_tuc" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Năm tốt nghiệp</label>
                <input type="number" x-model="newStudent.graduation_year" 
                       min="1900" max="{{ date('Y') + 10 }}" class="w-full rounded-lg border px-3 py-2">
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Bỏ học lớp</label>
                <select x-model="newStudent.bo_hoc_lop" class="w-full rounded-lg border px-3 py-2">
                  <option value="">-- Chọn lớp bỏ học --</option>
                  @foreach(\App\Models\EducationBlock::all() as $block)
                    <option value="{{ $block->name }}">{{ $block->name }}</option>
                  @endforeach
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Bỏ học năm</label>
                <input type="number" x-model="newStudent.bo_hoc_nam" 
                       class="w-full rounded-lg border px-3 py-2">
              </div>
            </div>
          </div>

          <!-- Thông tin khuyết tật -->
          <div class="mb-6">
            <h4 class="text-md font-semibold mb-3 text-orange-800">Thông tin khuyết tật</h4>
            <div class="grid gap-4 md:grid-cols-12">
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật vận động</label>
                <select x-model="newStudent.disability_van_dong" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật nghe, nói</label>
                <select x-model="newStudent.disability_nghe_noi" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật nhìn</label>
                <select x-model="newStudent.disability_nhin" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật thần kinh, tâm thần</label>
                <select x-model="newStudent.disability_than_kinh" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật trí tuệ</label>
                <select x-model="newStudent.disability_tri_tue" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật học tập</label>
                <select x-model="newStudent.disability_hoc_tap" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Tự kỷ</label>
                <select x-model="newStudent.disability_tu_ky" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật khác</label>
                <select x-model="newStudent.disability_khac" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Có chứng nhận khuyết tật</label>
                <select x-model="newStudent.disability_has_certificate" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Khuyết tật khả năng học tập</label>
                <select x-model="newStudent.disability_can_learn" class="w-full rounded-lg border px-3 py-2">
                  <option value="0">Không</option>
                  <option value="1">Có</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Thông tin hoàn cảnh -->
          <div class="mb-6">
            <h4 class="text-md font-semibold mb-3 text-pink-800">Thông tin hoàn cảnh</h4>
            <div class="grid gap-4 md:grid-cols-12">
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Hoàn cảnh đặc biệt</label>
                <select x-model="newStudent.special_circumstance_name" class="w-full rounded-lg border px-3 py-2">
                  <option value="">-- Chọn hoàn cảnh đặc biệt --</option>
                  @foreach(\App\Models\SpecialCircumstance::all() as $circumstance)
                    <option value="{{ $circumstance->name }}">{{ $circumstance->name }}</option>
                  @endforeach
                </select>
              </div>
              
              <div class="md:col-span-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Chi tiết hoàn cảnh đặc biệt</label>
                <input type="text" x-model="newStudent.special_circumstance_detail" 
                       class="w-full rounded-lg border px-3 py-2">
              </div>
            </div>
          </div>

          <!-- Buttons -->
          <div class="flex justify-end gap-3 pt-4 border-t">
            <button type="button" @click="showCreateModal = false" 
                    class="px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50">
              Hủy
            </button>
            <button type="submit" 
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
              Thêm mới
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection

<script>
function studentManagement() {
  return {
    showCreateModal: false,
    schoolSearch: '',
    showSchoolDropdown: false,
    filteredSchools: [],
    selectedSchool: null,
    allSchools: @json(\App\Models\School::select('id', 'code', 'name')->get()),
    newStudent: {
      ho_dem: '',
      ten: '',
      dob_day: '',
      dob_month: '',
      dob_year: '',
      identity_number: '',
      is_female: '0',
      ethnicity_id: '',
      religion_id: '',
      priority_category_id: '',
      education_block_id: '',
      class_label: '',
      school_id: '',
      graduation_level_id: '',
      academic_year: '{{ $academicYear }}',
      hoc_hai_buoi: '0',
      hoc_9_10_buoi_tuan: '0',
      is_bo_tuc: '0',
      graduation_year: '',
      bo_hoc_lop: '',
      bo_hoc_nam: '',
      disability_van_dong: '0',
      disability_nghe_noi: '0',
      disability_nhin: '0',
      disability_than_kinh: '0',
      disability_tri_tue: '0',
      disability_hoc_tap: '0',
      disability_tu_ky: '0',
      disability_khac: '0',
      disability_has_certificate: '0',
      disability_can_learn: '0',
      special_circumstance_name: '',
      special_circumstance_detail: ''
    },
    
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
      this.newStudent.school_id = school.id;
      this.schoolSearch = school.code + ' - ' + school.name;
      this.showSchoolDropdown = false;
    },
    
    clearSchool() {
      this.selectedSchool = null;
      this.newStudent.school_id = '';
      this.schoolSearch = '';
      this.filteredSchools = [];
    },
    
    async createStudent() {
      try {
        // Lấy dữ liệu geo từ form
        const geoData = this.getGeoData();
        
        const formData = {
          ...this.newStudent,
          ...geoData,
          _token: document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        };
        
        // Debug: log toàn bộ dữ liệu được gửi
        console.log('Full form data being sent:', formData);
        
        const response = await fetch('{{ route("front.students-off-track.store") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': formData._token
          },
          body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        // Debug: log response
        console.log('Response data:', data);
        console.log('Response status:', response.status);
        
        if (data.success) {
          alert('Thêm học sinh thành công!');
          this.showCreateModal = false;
          this.resetForm();
          // Reload trang để hiển thị học sinh mới
          window.location.reload();
        } else {
          console.error('Error response:', data);
          alert('Có lỗi xảy ra: ' + (data.message || 'Không thể thêm học sinh'));
          if (data.errors) {
            console.error('Validation errors:', data.errors);
            alert('Lỗi validation: ' + JSON.stringify(data.errors));
          }
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi thêm học sinh');
      }
    },
    
    getGeoData() {
      // Lấy dữ liệu nơi ở từ geo filter
      const stayProvinceSelect = document.querySelector('select[name="stay_geo[province_id]"]');
      const stayWardSelect = document.querySelector('select[name="stay_geo[ward_id]"]');
      
      // Lấy dữ liệu geo filter hiện tại (để lưu vào selected_*_new)
      const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
      let currentGeo = {};
      
      // Cách 1: Truy cập qua Alpine.js data stack
      if (geoFilterElement && geoFilterElement._x_dataStack) {
        const geoFilterData = geoFilterElement._x_dataStack[0];
        if (geoFilterData) {
          currentGeo = {
            'current_geo': {
              'province_id': geoFilterData.selProvNew || '',
              'ward_id': geoFilterData.selWardNew || '',
              'village_id': geoFilterData.selHamletNew || ''
            }
          };
        }
      }
      
      // Cách 2: Fallback - truy cập trực tiếp các select elements của geo filter chính
      if (!currentGeo['current_geo']) {
        const mainProvinceSelect = document.querySelector('select[name="geo[province_id]"]');
        const mainWardSelect = document.querySelector('select[name="geo[ward_id]"]');
        const mainVillageSelect = document.querySelector('select[name="geo[village_id]"]');
        
        currentGeo = {
          'current_geo': {
            'province_id': mainProvinceSelect ? mainProvinceSelect.value : '',
            'ward_id': mainWardSelect ? mainWardSelect.value : '',
            'village_id': mainVillageSelect ? mainVillageSelect.value : ''
          }
        };
      }
      
      const result = {
        // Thông tin nơi ở từ geo filter - gửi dưới dạng nested object
        'stay_geo': {
          'province_id': stayProvinceSelect ? stayProvinceSelect.value : '',
          'ward_id': stayWardSelect ? stayWardSelect.value : ''
        },
        // Geo filter hiện tại
        ...currentGeo
      };
      
      // Debug: log để kiểm tra
      console.log('Geo filter element:', geoFilterElement);
      console.log('Current geo data:', currentGeo);
      console.log('Stay province select:', stayProvinceSelect);
      console.log('Stay ward select:', stayWardSelect);
      console.log('Final geo data result:', result);
      
      return result;
    },
    
    resetForm() {
      this.newStudent = {
        ho_dem: '',
        ten: '',
        dob_day: '',
        dob_month: '',
        dob_year: '',
        identity_number: '',
        is_female: '0',
        ethnicity_id: '',
        religion_id: '',
        priority_category_id: '',
        education_block_id: '',
        class_label: '',
        school_id: '',
        graduation_level_id: '',
        academic_year: '{{ $academicYear }}',
        hoc_hai_buoi: '0',
        hoc_9_10_buoi_tuan: '0',
        is_bo_tuc: '0',
        graduation_year: '',
        bo_hoc_lop: '',
        bo_hoc_nam: '',
        disability_van_dong: '0',
        disability_nghe_noi: '0',
        disability_nhin: '0',
        disability_than_kinh: '0',
        disability_tri_tue: '0',
        disability_hoc_tap: '0',
        disability_tu_ky: '0',
        disability_khac: '0',
        disability_has_certificate: '0',
        disability_can_learn: '0',
        special_circumstance_name: '',
        special_circumstance_detail: ''
      };
      
      // Reset school search
      this.schoolSearch = '';
      this.selectedSchool = null;
      this.filteredSchools = [];
      this.showSchoolDropdown = false;
    }
  };
}

function bulkSelect(){
  return {
    selectedCount: 0,
    toggleAll(ev){
      const checked = ev.target.checked;
      document.querySelectorAll('input.row-checkbox').forEach(cb => {
        cb.checked = checked;
      });
      this.recount();
    },
    onRowToggle(){
      this.recount();
    },
    recount(){
      this.selectedCount = document.querySelectorAll('input.row-checkbox:checked').length;
    },
    confirmBulkDelete(){
      if (this.selectedCount === 0) {
        alert('Vui lòng chọn ít nhất một học sinh để xóa.');
        return;
      }
      
      if (confirm(`Bạn có chắc chắn muốn xóa ${this.selectedCount} học sinh đã chọn? Học sinh sẽ không hiển thị trong danh sách nữa.`)) {
        this.submitBulkDelete();
      }
    },
    async submitBulkDelete(){
      const form = this.$refs.bulkSelForm;
      const formData = new FormData(form);
      
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          },
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          alert(data.message);
          // Reload trang để giữ nguyên URL và bộ lọc
          window.location.reload();
        } else {
          alert('Có lỗi xảy ra khi xóa học sinh.');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('Có lỗi xảy ra khi xóa học sinh.');
      }
    }
  }
}

// Xử lý confirm dialog cho các form có data-confirm
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const confirmMessage = this.getAttribute('data-confirm');
      const title = this.getAttribute('data-title') || 'Xác nhận';
      const okText = this.getAttribute('data-ok') || 'OK';
      const cancelText = this.getAttribute('data-cancel') || 'Hủy';
      
      if (confirm(confirmMessage)) {
        const formData = new FormData(this);
        
        try {
          const response = await fetch(this.action, {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: formData
          });
          
          const data = await response.json();
          
          if (data.success) {
            alert(data.message);
            // Reload trang để giữ nguyên URL và bộ lọc
            window.location.reload();
          } else {
            alert('Có lỗi xảy ra khi xóa học sinh.');
          }
        } catch (error) {
          console.error('Error:', error);
          alert('Có lỗi xảy ra khi xóa học sinh.');
        }
      }
    });
  });
});
</script>