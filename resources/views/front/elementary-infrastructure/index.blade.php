@extends('layouts.front')
@section('title','Báo cáo cơ sở vật chất trường tiểu học')

@section('content')
<style>
    /* CSS cho bảng dựa trên mẫu mau.blade.php */
    .data-table {
        border-collapse: collapse;
        width: 100%;
        text-align: center;
        font-size: 13px;
    }

    .data-table th, 
    .data-table td {
        border: 1px solid #000;
        padding: 4px;
    }

    .data-table thead th {
        background: #f8f8f8;
    }

    /* chữ dọc */
    .vertical {
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        white-space: nowrap;
        padding: 6px 2px;
        font-size: 12px;
    }

    /* chữ ngang với xuống dòng */
    .horizontal {
        white-space: normal;
        padding: 4px 2px;
        font-size: 12px;
        line-height: 1.2;
        text-align: center;
        vertical-align: middle;
    }

    /* Màu sắc cho các nhóm cột */
    .group-truong {
        background-color: #fff2cc;
    }

    .group-so-lop {
        background-color: #dae8fc;
    }

    .group-phong-hoc {
        background-color: #d5e8d4;
    }

    .group-phong-chuc-nang {
        background-color: #f8cecc;
    }

    .group-cong-trinh-ve-sinh {
        background-color: #e1f5fe;
    }

    .group-san-choi {
        background-color: #f3e5f5;
    }

    .group-bai-tap {
        background-color: #f0f8ff;
    }

    /* Màu sắc cho các nhóm con */
    .subgroup-giao-vien {
        background-color: #e8f5e8;
    }

    .subgroup-hoc-sinh {
        background-color: #fff3e0;
    }

    .subgroup-phong-hop {
        background-color: #f0f8ff;
    }

    .subgroup-thu-vien {
        background-color: #f5f0ff;
    }

    .subgroup-thiet-bi {
        background-color: #fff8dc;
    }

    /* Sticky column cho cột TT và Đ.tr */
    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 10;
        background: white;
        min-width: 30px;
        max-width: 30px;
    }
    .sticky-col-2 {
        position: sticky;
        left: 30px;
        z-index: 10;
        background: white;
        min-width: 40px;
        max-width: 40px;
    }

    /* Hover effect */
    tbody tr:hover {
        background-color: #f5f5f5;
    }

    /* Table container */
    .table-container {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: white;
    }
</style>

<div class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between py-3">
     <h1 class="text-xl font-semibold">Báo cáo cơ sở vật chất trường Tiểu học</h1>
    <div class="flex gap-2">
      @if(request()->has('year') && request('year'))
        <button type="button" id="importExcelBtn" 
                class="rounded-lg bg-green-600 px-3 py-2 text-white hover:bg-green-700">
          <i class="fas fa-file-excel mr-2"></i>Nhập Excel
        </button>
        <a href="{{ route('front.elementary-infrastructure.export-excel', request()->query()) }}" 
           class="rounded-lg bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">
          Xuất Excel theo mẫu
        </a>
      @endif
    </div>
  </div>

  {{-- Nút chuyển đổi cho trường liên cấp --}}
  @if(auth()->user()->isSchool() && auth()->user()->school)
    @php
      $schoolLevel = auth()->user()->school->educationalLevel->name ?? '';
      $level = strtolower($schoolLevel);
    @endphp
    @if(str_contains($level, 'thcs') && str_contains($level, 'tiểu học'))
      <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fas fa-info-circle text-yellow-600"></i>
            <span class="text-sm text-yellow-800">Trường liên cấp: {{ $schoolLevel }}</span>
          </div>
          <a href="{{ route('front.primary-school-infrastructure.index', request()->query()) }}" 
             class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
            <i class="fas fa-exchange-alt mr-1"></i>Chuyển sang THCS
          </a>
        </div>
      </div>
    @endif
  @endif


  <form method="get" class="mb-6">
    {{-- Geo filter --}}
    <div class="mb-4">
      @include('partials.front.geo-filter', [
        'namePrefix' => 'geo',
        'scheme' => request('scheme', 'old'),
        'provinceId' => request('geo.province_id'),
        'districtId' => request('geo.district_id'),
        'wardId' => request('geo.ward_id'),
        'villageId' => request('geo.village_id'),
        'showHamlet' => false,
        'showSchool' => true,
        'enableSchool' => true,
        'schoolName' => 'geo.school_id',
        'schoolId' => request('geo.school_id'),
        'schoolDataUrl' => route('front.schools.datapicker'),
        'wrapperClass' => 'grid md:grid-cols-12 gap-3'
      ])
    </div>

    {{-- Các trường bổ sung --}}
    <div class="grid gap-3 md:grid-cols-12 mb-4">
      <div class="md:col-span-3">
        <label class="block text-xs text-slate-500 mb-1">Năm học</label>
        <select name="year">
          @for($i = date('Y') - 5; $i <= date('Y') + 2; $i++)
            @php
              $academicYear = $i . '-' . ($i + 1);
            @endphp
            <option value="{{ $academicYear }}" @selected(request('year') == $academicYear)>{{ $academicYear }}</option>
          @endfor
        </select>
      </div>


      <div class="md:col-span-6 flex items-end gap-2">
        <button class="btn-primary">Lọc</button>
        <a href="{{ route('front.elementary-infrastructure.index') }}" class="btn-soft">Xoá lọc</a>
      </div>
    </div>
  </form>


  {{-- Thông tin trường học --}}
  @if($reports->isNotEmpty())
    @php
      $report = $reports->first();
      $school = \App\Models\School::find($geoIds['school']);
    @endphp
    @if($school)
      <div class="mb-4 rounded-lg bg-blue-50 p-4 border border-blue-200">
        <div class="flex justify-between items-center mb-2">
          <h3 class="text-lg font-semibold text-blue-800">Thông tin trường Tiểu học</h3>
          <button id="editDataBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
            <i class="fas fa-edit mr-2"></i>Chỉnh sửa dữ liệu
          </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <span class="text-sm text-blue-600 font-medium">Tên trường:</span>
            <span class="text-sm text-blue-800">{{ $school->name }}</span>
          </div>
          <div>
            <span class="text-sm text-blue-600 font-medium">Mã trường:</span>
            <span class="text-sm text-blue-800">{{ $school->code }}</span>
          </div>
          <div>
            <span class="text-sm text-blue-600 font-medium">Năm học:</span>
            <span class="text-sm text-blue-800">{{ $year }}</span>
          </div>
        </div>
      </div>
    @endif
  @endif

  {{-- Popup Modal cho form nhập dữ liệu cấp trường --}}
  @if($reports->isNotEmpty())
    @php
      $report = $reports->first();
    @endphp
    <!-- Modal nhập Excel -->
    <div id="importExcelModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
      <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
          <!-- Modal Header -->
          <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Nhập dữ liệu từ Excel</h3>
            <button id="closeImportModal" class="text-gray-400 hover:text-gray-600">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
          
          <!-- Modal Body -->
          <form id="importExcelForm" class="p-6" enctype="multipart/form-data">
            @csrf
            
            {{-- Hidden inputs cho thông tin địa lý --}}
            <input type="hidden" name="geo[province_id]" value="{{ request('geo.province_id') }}">
            <input type="hidden" name="geo[ward_id]" value="{{ request('geo.ward_id') }}">
            <input type="hidden" name="geo[school_id]" value="{{ request('geo.school_id') }}">
            
            {{-- Thông tin địa lý --}}
            <div class="mb-6">
              <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Thông tin địa lý</h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Tỉnh/Thành phố</label>
                  <input type="text" id="importProvince" class="w-full border border-gray-300 rounded px-3 py-2 bg-gray-100" readonly>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Xã/Phường</label>
                  <input type="text" id="importWard" class="w-full border border-gray-300 rounded px-3 py-2 bg-gray-100" readonly>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Trường</label>
                  <input type="text" id="importSchool" class="w-full border border-gray-300 rounded px-3 py-2 bg-gray-100" readonly>
                </div>
              </div>
            </div>

            {{-- Chọn file Excel --}}
            <div class="mb-6">
              <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Chọn file Excel</h4>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">File Excel (.xlsx, .xls)</label>
                <input type="file" name="excel_file" id="excelFile" accept=".xlsx,.xls" 
                       class="w-full border border-gray-300 rounded px-3 py-2" required>
                <p class="text-xs text-gray-500 mt-1">Chỉ chấp nhận file Excel (.xlsx, .xls). Dữ liệu sẽ được đọc từ dòng 10, cột E đến AE.</p>
              </div>
            </div>

            {{-- Năm học --}}
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-1">Năm học</label>
              <select name="year" id="importYear" class="w-full border border-gray-300 rounded px-3 py-2" required>
                @for($i = date('Y') - 5; $i <= date('Y') + 2; $i++)
                  @php
                    $academicYear = $i . '-' . ($i + 1);
                  @endphp
                  <option value="{{ $academicYear }}" @selected(request('year') == $academicYear)>{{ $academicYear }}</option>
                @endfor
              </select>
            </div>
          </form>

          <!-- Modal Footer -->
          <div class="flex justify-end space-x-3 p-6 border-t">
            <button type="button" id="cancelImportBtn" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
              Hủy
            </button>
            <button type="submit" id="importDataBtn" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
              <i class="fas fa-upload mr-2"></i>Nhập dữ liệu
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal chỉnh sửa dữ liệu -->
    <div id="editDataModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
      <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Chỉnh sửa dữ liệu báo cáo cơ sở vật chất</h3>
            <button id="closeModal" class="text-gray-400 hover:text-gray-600">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
          
          <!-- Modal Body -->
          <form id="schoolDataForm" class="p-6">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $year }}">
            <input type="hidden" name="school_id" value="{{ $report->school_id ?? '' }}">
            <input type="hidden" name="ward_id" value="{{ $report->ward_id ?? '' }}">
            <input type="hidden" name="school_name" value="{{ $report->school_name ?? '' }}">
            
            {{-- Form theo đúng thứ tự bảng --}}
            <div class="space-y-6">
              {{-- Thông tin trường --}}
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Điểm trường</label>
                  <input type="number" name="school_branch" value="{{ $report->school_branch ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                </div>
              </div>

              {{-- Số lớp --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Số lớp</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label>
                    <input type="number" name="total_classes" value="{{ $report->total_classes ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Lớp ghép</label>
                    <input type="number" name="combined_classes" value="{{ $report->combined_classes ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Số phòng học --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Số phòng học</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kiên cố</label>
                    <input type="number" name="permanent_classrooms" value="{{ $report->permanent_classrooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bán kiên cố</label>
                    <input type="number" name="semi_permanent_classrooms" value="{{ $report->semi_permanent_classrooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tạm</label>
                    <input type="number" name="temporary_classrooms" value="{{ $report->temporary_classrooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Thuê/mượn</label>
                    <input type="number" name="rented_classrooms" value="{{ $report->rented_classrooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tỷ lệ Ph/L</label>
                    <input type="number" name="room_class_ratio" value="{{ $report->room_class_ratio ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Số phòng chức năng --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Số phòng chức năng</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">HT</label>
                    <input type="number" name="principal_room" value="{{ $report->principal_room ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phó HT</label>
                    <input type="number" name="vice_principal_room" value="{{ $report->vice_principal_room ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">VP</label>
                    <input type="number" name="office_room" value="{{ $report->office_room ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Y tế</label>
                    <input type="number" name="medical_room" value="{{ $report->medical_room ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TT HĐ Đội</label>
                    <input type="number" name="youth_union_room" value="{{ $report->youth_union_room ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Phòng họp --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Phòng họp</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="meeting_room_quantity" value="{{ $report->meeting_room_quantity ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích (m²)</label>
                    <input type="number" name="meeting_room_area" value="{{ $report->meeting_room_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Thư viện --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Thư viện</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="library_room_quantity" value="{{ $report->library_room_quantity ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích (m²)</label>
                    <input type="number" name="library_room_area" value="{{ $report->library_room_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Thiết bị --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Thiết bị</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="equipment_room_quantity" value="{{ $report->equipment_room_quantity ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích (m²)</label>
                    <input type="number" name="equipment_room_area" value="{{ $report->equipment_room_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Công trình vệ sinh - Giáo viên --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Công trình vệ sinh - Giáo viên</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="teacher_toilet_quantity" value="{{ $report->teacher_toilet_quantity ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích (m²)</label>
                    <input type="number" name="teacher_toilet_area" value="{{ $report->teacher_toilet_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Công trình vệ sinh - Học sinh --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Công trình vệ sinh - Học sinh</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="student_toilet_quantity" value="{{ $report->student_toilet_quantity ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích (m²)</label>
                    <input type="number" name="student_toilet_area" value="{{ $report->student_toilet_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Sân chơi --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Sân chơi</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="playground_quantity" value="{{ $report->playground_quantity ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích (m²)</label>
                    <input type="number" name="playground_area" value="{{ $report->playground_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Bãi tập --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Bãi tập</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="practice_field_quantity" value="{{ $report->practice_field_quantity ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích (m²)</label>
                    <input type="number" name="practice_field_area" value="{{ $report->practice_field_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="mt-6 flex justify-end space-x-3 pt-6 border-t">
              <button type="button" id="resetForm" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                Làm mới
              </button>
              <button type="button" id="closeModalBtn" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                Hủy
              </button>
              <button type="submit" id="saveData" class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                <i class="fas fa-save mr-2"></i>Lưu dữ liệu
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif


  {{-- Thông báo hướng dẫn --}}
  @if(!request()->has('year') || !request('year'))
    <div class="mb-4 rounded-lg bg-blue-50 p-4 border border-blue-200">
      <div class="flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-600"></i>
        <span class="text-sm font-medium text-blue-800">
          Chọn năm và nhấn Lọc để xem và chỉnh sửa dữ liệu
        </span>
      </div>
    </div>
  @endif

  {{-- DESKTOP TABLE --}}
  <div class="table-container">
    <table class="data-table">
      <thead>
        {{-- Hàng 1: tiêu đề nhóm chính --}}
        <tr>
          <th rowspan="3" class="horizontal">TT</th>
            <th class="group-truong horizontal" rowspan="3">Đ.tr</th>
          <th class="group-so-lop" colspan="2">Số lớp</th>
          <th class="group-phong-hoc" colspan="5">Số phòng học</th>
          <th class="group-phong-chuc-nang" colspan="11">Số phòng chức năng</th>
          <th class="group-cong-trinh-ve-sinh" colspan="4">Công trình vệ sinh</th>
          <th class="group-san-choi" colspan="2">Sân chơi</th>
          <th class="group-bai-tap" colspan="2">Bãi tập</th>
        </tr>

        {{-- Hàng 2: tiêu đề nhóm con --}}
        <tr>
          <th class="group-so-lop horizontal" rowspan="2">Tổng<br>số</th>
          <th class="group-so-lop horizontal" rowspan="2">Lớp<br>ghép</th>
          <th class="group-phong-hoc horizontal" rowspan="2">Kiên<br>cố</th>
          <th class="group-phong-hoc horizontal" rowspan="2">Bán<br>kiên cố</th>
          <th class="group-phong-hoc horizontal" rowspan="2">Tạm</th>
          <th class="group-phong-hoc horizontal" rowspan="2">Thuê/<br>mượn</th>
          <th class="group-phong-hoc horizontal" rowspan="2">Tỷ lệ<br>Ph/L</th>
          <th class="group-phong-chuc-nang horizontal" rowspan="2">HT</th>
          <th class="group-phong-chuc-nang horizontal" rowspan="2">Phó<br>HT</th>
          <th class="group-phong-chuc-nang horizontal" rowspan="2">VP</th>
          <th class="group-phong-chuc-nang horizontal" rowspan="2">Y tế</th>
          <th class="group-phong-chuc-nang horizontal" rowspan="2">TT HĐ<br>Đội</th>
          <th class="subgroup-phong-hop" colspan="2">Phòng họp</th>
          <th class="subgroup-thu-vien" colspan="2">Thư viện</th>
          <th class="subgroup-thiet-bi" colspan="2">Thiết bị</th>
          <th class="subgroup-giao-vien" colspan="2">Giáo viên</th>
          <th class="subgroup-hoc-sinh" colspan="2">Học sinh</th>
          <th class="group-san-choi horizontal" rowspan="2">Số<br>lượng</th>
          <th class="group-san-choi horizontal" rowspan="2">Diện<br>tích</th>
          <th class="group-bai-tap horizontal" rowspan="2">Số<br>lượng</th>
          <th class="group-bai-tap horizontal" rowspan="2">Diện<br>tích</th>
        </tr>

        {{-- Hàng 3: tiêu đề cột chi tiết --}}
        <tr>
          {{-- Phòng họp --}}
          <th class="subgroup-phong-hop horizontal">SL</th>
          <th class="subgroup-phong-hop horizontal">DT</th>
          {{-- Thư viện --}}
          <th class="subgroup-thu-vien horizontal">SL</th>
          <th class="subgroup-thu-vien horizontal">DT</th>
          {{-- Thiết bị --}}
          <th class="subgroup-thiet-bi horizontal">SL</th>
          <th class="subgroup-thiet-bi horizontal">DT</th>
          {{-- Nhà vệ sinh GV --}}
          <th class="subgroup-giao-vien horizontal">SL</th>
          <th class="subgroup-giao-vien horizontal">DT</th>
          {{-- Nhà vệ sinh HS --}}
          <th class="subgroup-hoc-sinh horizontal">SL</th>
          <th class="subgroup-hoc-sinh horizontal">DT</th>
        </tr>
      </thead>
      <tbody>
          @forelse($reports as $index => $report)
            <tr class="hover-bg-slate-50" id="schoolDataRow">
            <td class="sticky-col">
              @if(method_exists($reports, 'firstItem'))
                {{ $reports->firstItem() + $index }}
              @else
                {{ $index + 1 }}
              @endif
            </td>
            
            {{-- Điểm trường --}}
            <td class="sticky-col-2 text-center">{{ $report->school_branch ?? 0 }}</td>
            
            {{-- Số lớp --}}
            <td class="text-center">
              <span>{{ $report->total_classes ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->combined_classes ?? 0 }}</span>
            </td>
            
            {{-- Số phòng học --}}
            <td class="text-center">
              <span>{{ $report->permanent_classrooms ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->semi_permanent_classrooms ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->temporary_classrooms ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->rented_classrooms ?? 0 }}</span>
            </td>
            <td class="text-center">
              @if($level === 'school')
                <span>@formatNumber($report->room_class_ratio ?? 0, 2)</span>
              @else
                @formatNumber($report->room_class_ratio ?? 0, 2)
              @endif
            </td>
            
            {{-- Số phòng chức năng --}}
            <td class="text-center">
              <span>{{ $report->principal_room ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->vice_principal_room ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->office_room ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->medical_room ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->youth_union_room ?? 0 }}</span>
            </td>
            
            {{-- Phòng họp --}}
            <td class="text-center">
              <span>{{ $report->meeting_room_quantity ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>@formatNumber($report->meeting_room_area ?? 0, 2)</span>
            </td>
            
            {{-- Thư viện --}}
            <td class="text-center">
              <span>{{ $report->library_room_quantity ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>@formatNumber($report->library_room_area ?? 0, 2)</span>
            </td>
            
            {{-- Thiết bị --}}
            <td class="text-center">
              <span>{{ $report->equipment_room_quantity ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>@formatNumber($report->equipment_room_area ?? 0, 2)</span>
            </td>
            
            {{-- Công trình vệ sinh - Giáo viên --}}
            <td class="text-center">
              <span>{{ $report->teacher_toilet_quantity ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>@formatNumber($report->teacher_toilet_area ?? 0, 2)</span>
            </td>
            
            {{-- Công trình vệ sinh - Học sinh --}}
            <td class="text-center">
              <span>{{ $report->student_toilet_quantity ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>@formatNumber($report->student_toilet_area ?? 0, 2)</span>
            </td>
            
            {{-- Sân chơi --}}
            <td class="text-center">
              <span>{{ $report->playground_quantity ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>@formatNumber($report->playground_area ?? 0, 2)</span>
            </td>
            
            {{-- Bãi tập --}}
            <td class="text-center">
              <span>{{ $report->practice_field_quantity ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>@formatNumber($report->practice_field_area ?? 0, 2)</span>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="28" class="text-center text-slate-500">
              Không có dữ liệu
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- MOBILE CARDS --}}
  <div class="md:hidden space-y-4">
    @forelse($reports as $report)
      <div class="rounded-lg border bg-white p-4 shadow-sm">
        <div class="mb-2 flex items-center justify-between">
            <h3 class="font-semibold">
              {{ $report->school_name ?? '' }}
            </h3>
          <span class="text-sm text-slate-500">{{ $report->academic_year }}</span>
        </div>
        
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div>
            <span class="text-slate-600">Điểm trường:</span>
            <span class="font-medium">{{ $report->school_branch ?? '' }}</span>
          </div>
          <div>
            <span class="text-slate-600">Tổng lớp:</span>
            <span class="font-medium">{{ $report->total_classes ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Lớp ghép:</span>
            <span class="font-medium">{{ $report->combined_classes ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Phòng kiên cố:</span>
            <span class="font-medium">{{ $report->permanent_classrooms ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Tỷ lệ Ph/L:</span>
            <span class="font-medium">@formatNumber($report->room_class_ratio ?? 0, 2)</span>
          </div>
          <div>
            <span class="text-slate-600">Phòng HT:</span>
            <span class="font-medium">{{ $report->principal_room ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Thư viện:</span>
            <span class="font-medium">{{ $report->library_room_quantity ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Sân chơi:</span>
            <span class="font-medium">{{ $report->playground_quantity ?? 0 }}</span>
          </div>
        </div>
        
        <div class="mt-2 text-xs text-slate-500">
          {{ $report->ward?->name }} - {{ $report->ward?->parent?->name }} - {{ $report->ward?->parent?->parent?->name }}
        </div>
      </div>
    @empty
      <div class="rounded-lg border bg-white p-8 text-center text-slate-500">
        Không có dữ liệu
      </div>
    @endforelse
  </div>

  {{-- Pagination --}}
  @if(method_exists($reports, 'hasPages') && $reports->hasPages())
    <div class="mt-4">
      {{ $reports->links() }}
    </div>
  @endif
</div>

@if($reports->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Import Excel Modal
    const importBtn = document.getElementById('importExcelBtn');
    const importModal = document.getElementById('importExcelModal');
    const closeImportModal = document.getElementById('closeImportModal');
    const cancelImportBtn = document.getElementById('cancelImportBtn');
    const importForm = document.getElementById('importExcelForm');
    const importDataBtn = document.getElementById('importDataBtn');
    
    // Edit Data Modal
    const editBtn = document.getElementById('editDataBtn');
    const modal = document.getElementById('editDataModal');
    const closeModal = document.getElementById('closeModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const form = document.getElementById('schoolDataForm');
    const saveButton = document.getElementById('saveData');
    const resetButton = document.getElementById('resetForm');
    
    // Mở modal nhập Excel
    if (importBtn && importModal) {
        importBtn.addEventListener('click', function() {
            importModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Điền thông tin địa lý
            updateImportGeoInfo();
        });
    }
    
    // Mở modal chỉnh sửa
    if (editBtn && modal) {
        editBtn.addEventListener('click', function() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Ngăn scroll
        });
    }
    
    // Đóng modal nhập Excel
    function closeImportModalFunc() {
        importModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        importForm.reset();
    }
    
    // Đóng modal chỉnh sửa
    function closeModalFunc() {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto'; // Khôi phục scroll
    }
    
    // Event listeners cho modal nhập Excel
    if (closeImportModal) {
        closeImportModal.addEventListener('click', closeImportModalFunc);
    }
    
    if (cancelImportBtn) {
        cancelImportBtn.addEventListener('click', closeImportModalFunc);
    }
    
    // Event listeners cho modal chỉnh sửa
    if (closeModal) {
        closeModal.addEventListener('click', closeModalFunc);
    }
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModalFunc);
    }
    
    // Đóng modal khi click outside
    if (importModal) {
        importModal.addEventListener('click', function(e) {
            if (e.target === importModal) {
                closeImportModalFunc();
            }
        });
    }
    
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModalFunc();
            }
        });
    }
    
    // Đóng modal bằng ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (!importModal.classList.contains('hidden')) {
                closeImportModalFunc();
            } else if (!modal.classList.contains('hidden')) {
                closeModalFunc();
            }
        }
    });
    
    // Xử lý form nhập Excel
    if (importForm) {
        importForm.addEventListener('submit', function(e) {
            e.preventDefault();
            importExcelData();
        });
    }
    
    // Xử lý nút nhập dữ liệu
    if (importDataBtn) {
        importDataBtn.addEventListener('click', function(e) {
            e.preventDefault();
            importExcelData();
        });
    }
    
    // Xử lý form chỉnh sửa
    if (form && saveButton) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveSchoolData();
        });
    }
    
    // Xử lý reset form
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            if (confirm('Bạn có chắc chắn muốn làm mới form? Tất cả dữ liệu chưa lưu sẽ bị mất.')) {
                form.reset();
            }
        });
    }
});

function updateImportGeoInfo() {
    // Lấy thông tin từ geo filter hiện tại (Alpine.js)
    const provinceInput = document.getElementById('importProvince');
    const wardInput = document.getElementById('importWard');
    const schoolInput = document.getElementById('importSchool');
    
    // Lấy tên hiển thị từ Alpine.js components
    try {
        // Tỉnh/Thành phố
        const provinceDisplay = document.querySelector('[x-text="displayProvinceName()"]');
        if (provinceDisplay && provinceInput) {
            provinceInput.value = provinceDisplay.textContent || '';
        }
        
        // Xã/Phường
        const wardDisplay = document.querySelector('[x-text="displayWardName()"]');
        if (wardDisplay && wardInput) {
            wardInput.value = wardDisplay.textContent || '';
        }
        
        // Trường
        const schoolDisplay = document.querySelector('[x-text="displaySchoolName()"]');
        if (schoolDisplay && schoolInput) {
            schoolInput.value = schoolDisplay.textContent || '';
        }
        
        // Fallback: lấy từ hidden inputs nếu không có display
        if (!provinceInput.value) {
            const provinceHidden = document.querySelector('input[name="geo[province_id]"]');
            if (provinceHidden && provinceHidden.value) {
                provinceInput.value = 'ID: ' + provinceHidden.value;
            }
        }
        
        if (!wardInput.value) {
            const wardHidden = document.querySelector('input[name="geo[ward_id]"]');
            if (wardHidden && wardHidden.value) {
                wardInput.value = 'ID: ' + wardHidden.value;
            }
        }
        
        if (!schoolInput.value) {
            const schoolHidden = document.querySelector('input[name="geo[school_id]"]');
            if (schoolHidden && schoolHidden.value) {
                schoolInput.value = 'ID: ' + schoolHidden.value;
            }
        }
        
    } catch (error) {
        // Fallback values
        if (provinceInput) provinceInput.value = 'Chưa chọn';
        if (wardInput) wardInput.value = 'Chưa chọn';
        if (schoolInput) schoolInput.value = 'Chưa chọn';
    }
}

function importExcelData() {
    const form = document.getElementById('importExcelForm');
    if (!form) return;
    
    const fileInput = document.getElementById('excelFile');
    if (!fileInput.files.length) {
        showNotification('Vui lòng chọn file Excel', 'error');
        return;
    }
    
    const formData = new FormData(form);
    const importBtn = document.getElementById('importDataBtn');
    const originalText = importBtn.innerHTML;
    
    // Disable button và hiển thị loading
    importBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang nhập...';
    importBtn.disabled = true;
    
    fetch('{{ route("front.elementary-infrastructure.import-excel") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Nhập dữ liệu thành công!', 'success');
            setTimeout(() => {
                document.getElementById('importExcelModal').classList.add('hidden');
                document.body.style.overflow = 'auto';
                location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Có lỗi xảy ra khi nhập dữ liệu', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi nhập dữ liệu', 'error');
    })
    .finally(() => {
        // Khôi phục button
        importBtn.innerHTML = originalText;
        importBtn.disabled = false;
    });
}

function saveSchoolData() {
    const form = document.getElementById('schoolDataForm');
    if (!form) return;
    
    const formData = new FormData(form);
    const saveButton = document.getElementById('saveData');
    const originalText = saveButton.innerHTML;
    
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang lưu...';
    saveButton.disabled = true;
    
    fetch('{{ route("front.elementary-infrastructure.save-school-data") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Hiển thị thông báo thành công
            showNotification('Lưu dữ liệu thành công!', 'success');
            // Đóng modal và reload trang
            setTimeout(() => {
                document.getElementById('editDataModal').classList.add('hidden');
                document.body.style.overflow = 'auto';
                location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi: ' + (data.message || 'Có lỗi xảy ra'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi lưu dữ liệu', 'error');
    })
    .finally(() => {
        saveButton.innerHTML = originalText;
        saveButton.disabled = false;
    });
}

function showNotification(message, type = 'info') {
    // Tạo thông báo
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        'bg-blue-500'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Tự động ẩn sau 3 giây
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
@endif
@endsection