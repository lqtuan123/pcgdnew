@extends('layouts.front')

@section('title', 'Báo cáo cơ sở vật chất trường Trung học cơ sở')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between py-3">
    <h1 class="text-xl font-semibold">Báo cáo cơ sở vật chất trường Trung học cơ sở</h1>
    <div class="flex gap-2">
      @if(request()->has('year') && request('year'))
        <button id="importExcelBtn" class="rounded-lg bg-green-600 px-3 py-2 text-white hover:bg-green-700">
          <i class="fas fa-upload mr-2"></i>Nhập Excel
        </button>
        <a href="{{ route('front.primary-school-infrastructure.export-excel', request()->query()) }}" 
           class="rounded-lg bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">
          <i class="fas fa-download mr-2"></i>Xuất Excel theo mẫu
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
          <a href="{{ route('front.elementary-infrastructure.index', request()->query()) }}" 
             class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
            <i class="fas fa-exchange-alt mr-1"></i>Chuyển sang Tiểu học
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

      <div class="md:col-span-9 flex items-end gap-2">
        <button class="btn-primary">Lọc</button>
        <a href="{{ route('front.primary-school-infrastructure.index') }}" class="btn-soft">Xoá lọc</a>
      </div>
    </div>
  </form>

  {{-- Thông tin trường học --}}
  @if($reports->isNotEmpty())
    @php
      $report = $reports->first();
      $school = $geoIds['school'] ? \App\Models\School::find($geoIds['school']) : null;
    @endphp
    @if($school)
      <div class="mb-4 rounded-lg bg-blue-50 p-4 border border-blue-200">
        <div class="flex justify-between items-center mb-2">
          <h3 class="text-lg font-semibold text-blue-800">Thông tin trường THCS</h3>
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
    <!-- Modal -->
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
              {{-- Điểm trường --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Thông tin điểm trường</h4>
                <div class="grid grid-cols-1 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Điểm trường</label>
                    <input type="number" name="school_branch" value="{{ $report->school_branch ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" placeholder="Nhập số điểm trường" 
                           min="0" step="1" pattern="[0-9]*" inputmode="numeric">
                  </div>
                </div>
              </div>
              {{-- Số lớp --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Số lớp</h4>
                <div class="grid grid-cols-1 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lớp</label>
                    <input type="number" name="total_classes" value="{{ $report->total_classes ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Số phòng học --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Số phòng học</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kiên cố</label>
                    <input type="number" name="solid_classrooms" value="{{ $report->solid_classrooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bán kiên cố</label>
                    <input type="number" name="semi_solid_classrooms" value="{{ $report->semi_solid_classrooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tạm</label>
                    <input type="number" name="temporary_classrooms" value="{{ $report->temporary_classrooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tỷ lệ Ph/L</label>
                    <input type="number" name="classroom_class_ratio" value="{{ $report->classroom_class_ratio ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Số phòng chức năng --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Số phòng chức năng</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">HT</label>
                    <input type="number" name="principal_rooms_count" value="{{ $report->principal_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phó HT</label>
                    <input type="number" name="vice_principal_rooms_count" value="{{ $report->vice_principal_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">VP</label>
                    <input type="number" name="office_rooms_count" value="{{ $report->office_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Y tế</label>
                    <input type="number" name="medical_rooms_count" value="{{ $report->medical_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phòng họp</label>
                    <input type="number" name="meeting_rooms_count" value="{{ $report->meeting_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Thư viện</label>
                    <input type="number" name="library_rooms_count" value="{{ $report->library_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Phòng thí nghiệm --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Phòng thí nghiệm</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="equipment_rooms_count" value="{{ $report->equipment_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích</label>
                    <input type="number" name="equipment_rooms_area" value="{{ $report->equipment_rooms_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Giáo viên --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Công trình vệ sinh cho giáo viên</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="teacher_rooms_count" value="{{ $report->teacher_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích</label>
                    <input type="number" name="teacher_rooms_area" value="{{ $report->teacher_rooms_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Học sinh --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Công trình vệ sinh cho học sinh</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lượng</label>
                    <input type="number" name="student_rooms_count" value="{{ $report->student_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích</label>
                    <input type="number" name="student_rooms_area" value="{{ $report->student_rooms_area ?? 0 }}" 
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
                    <input type="number" name="playground_count" value="{{ $report->playground_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích</label>
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
                    <input type="number" name="training_ground_count" value="{{ $report->training_ground_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Diện tích</label>
                    <input type="number" name="training_ground_area" value="{{ $report->training_ground_area ?? 0 }}" 
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

  {{-- Modal nhập Excel --}}
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
              <p class="text-xs text-gray-500 mt-1">Chỉ chấp nhận file Excel (.xlsx, .xls)</p>
            </div>
          </div>

          {{-- Năm học --}}
          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Năm học</label>
            <select name="academic_year" id="importYear" class="w-full border border-gray-300 rounded px-3 py-2" required>
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
          <button type="button" id="closeImportModalBtn" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
            Hủy
          </button>
          <button type="button" id="uploadExcel" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
            <i class="fas fa-upload mr-2"></i>Nhập dữ liệu
          </button>
        </div>
      </div>
    </div>
  </div>

  {{-- Thông báo khi chưa chọn năm hoặc không có dữ liệu --}}
  @if((!request()->has('year') || !request('year')) || $reports->isEmpty())
    <div class="mb-4 rounded-lg bg-blue-50 p-4 border border-blue-200">
      <div class="flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-600"></i>
        <span class="text-sm font-medium text-blue-800">
          @if($reports->isEmpty())
            Chọn năm và nhấn Lọc để xem và chỉnh sửa dữ liệu cơ sở vật chất THCS
          @else
            Chọn năm và nhấn Lọc để xem và chỉnh sửa dữ liệu
          @endif
        </span>
      </div>
    </div>
  @endif

  {{-- DESKTOP TABLE --}}
  <div class="table-container">
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

      /* Sticky column cho cột TT */
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
      .sticky-col-3 {
        position: sticky;
        left: 70px;
        z-index: 10;
        background: white;
        min-width: 50px;
        max-width: 50px;
      }
      
      @if($level === 'school')
      .sticky-col-3 {
        display: none;
      }
      @endif

      /* Hover effect */
      tbody tr:hover {
        background-color: #f5f5f5;
      }

      /* Group styling */
      .group-lop {
        background-color: #fef3c7;
      }
      .group-phong {
        background-color: #dbeafe;
      }
      .group-chuc-nang {
        background-color: #dcfce7;
      }
      .group-vs {
        background-color: #f3e8ff;
      }
      .group-sanchoi {
        background-color: #fecaca;
      }
      .group-baitap {
        background-color: #e0e7ff;
      }

      /* Table container */
      .table-container {
        overflow-x: auto;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: white;
      }
    </style>

    <div class="table-container">
      <table class="data-table">
        <thead>
          {{-- Hàng 1: nhóm chính --}}
          <tr>
            <th rowspan="3" class="sticky-col">TT</th>
            <th rowspan="3" class="sticky-col-2">Đ.tr</th>
            @if($level === 'province')
              <th rowspan="3" class="sticky-col-3">Tên xã/phường</th>
            @elseif($level === 'ward')
              <th rowspan="3" class="sticky-col-3">Trường</th>
            @endif
            <th class="group-lop" rowspan="3">Số lớp</th>
            <th class="group-phong" colspan="4">Số phòng học</th>
            <th class="group-chuc-nang" colspan="8">Số phòng chức năng</th>
            <th class="group-vs" colspan="4">Công trình vệ sinh</th>
            <th class="group-sanchoi" colspan="2">Sân chơi</th>
            <th class="group-baitap" colspan="2">Bãi tập</th>
          </tr>

          {{-- Hàng 2: nhóm con --}}
          <tr>
            <th class="group-phong" rowspan="2">Kiên cố</th>
            <th class="group-phong" rowspan="2">Bán<br>kiên cố</th>
            <th class="group-phong" rowspan="2">Tạm</th>
            <th class="group-phong" rowspan="2">Tỷ lệ<br>Ph/L</th>
            <th class="group-chuc-nang" rowspan="2">HT</th>
            <th class="group-chuc-nang" rowspan="2">Phó HT</th>
            <th class="group-chuc-nang" rowspan="2">VP</th>
            <th class="group-chuc-nang" rowspan="2">Y tế</th>
            <th class="group-chuc-nang" rowspan="2">Phòng họp</th>
            <th class="group-chuc-nang" rowspan="2">Thư viện</th>
            <th class="group-chuc-nang" colspan="2">Phòng thí nghiệm</th>
            <th class="group-vs" colspan="2">Giáo viên</th>
            <th class="group-vs" colspan="2">Học sinh</th>
            <th class="group-sanchoi" rowspan="2">Số lượng</th>
            <th class="group-sanchoi" rowspan="2">Diện tích</th>
            <th class="group-baitap" rowspan="2">Số lượng</th>
            <th class="group-baitap" rowspan="2">Diện tích</th>
          </tr>

          {{-- Hàng 3: chi tiết chỉ số --}}
          <tr>
            <th class="group-chuc-nang">Số<br>lượng</th>
            <th class="group-chuc-nang">Diện<br>tích</th>
            <th class="group-chuc-nang">Số<br>lượng</th>
            <th class="group-chuc-nang">Diện<br>tích</th>
            <th class="group-chuc-nang">Số<br>lượng</th>
            <th class="group-chuc-nang">Diện<br>tích</th>
          </tr>
        </thead>
        <tbody>
          @forelse($reports as $index => $report)
          <tr class="hover-bg-slate-50">
            <td class="sticky-col">{{ $reports->firstItem() + $index }}</td>
            <td class="sticky-col-2 text-center">{{ $report->school_branch ?? '' }}</td>
            @if($level === 'province')
              <td class="sticky-col-3 text-center">{{ $report->ward_name ?? $report->ward?->name ?? '' }}</td>
            @elseif($level === 'ward')
              <td class="sticky-col-3 text-center">{{ $report->school_name ?? '' }}</td>
            @endif
            <td class="text-center">@formatNumber($report->total_classes, 0)</td>
            {{-- Debug: Hiển thị raw data --}}
            {{-- <td style="font-size: 8px; color: red;">{{ $report->school_branch }}</td> --}}
            <td class="text-center">@formatNumber($report->solid_classrooms, 0)</td>
            <td class="text-center">@formatNumber($report->semi_solid_classrooms, 0)</td>
            <td class="text-center">@formatNumber($report->temporary_classrooms, 0)</td>
            <td class="text-center">@formatNumber($report->classroom_class_ratio, 2)</td>
            <td class="text-center">@formatNumber($report->principal_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->vice_principal_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->office_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->medical_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->meeting_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->library_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->equipment_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->equipment_rooms_area, 2)</td>
            <td class="text-center">@formatNumber($report->teacher_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->teacher_rooms_area, 2)</td>
            <td class="text-center">@formatNumber($report->student_rooms_count, 0)</td>
            <td class="text-center">@formatNumber($report->student_rooms_area, 2)</td>
            <td class="text-center">@formatNumber($report->playground_count, 0)</td>
            <td class="text-center">@formatNumber($report->playground_area, 2)</td>
            <td class="text-center">@formatNumber($report->training_ground_count, 0)</td>
            <td class="text-center">@formatNumber($report->training_ground_area, 2)</td>
          </tr>
          @empty
          <tr>
            <td colspan="{{ $level === 'school' ? 22 : ($level === 'ward' ? 23 : 23) }}" class="text-center text-slate-500">
              @if($level === 'school')
                Vui lòng chọn trường Trung học cơ sở để nhập dữ liệu
              @else
                Không có dữ liệu trường Trung học cơ sở
              @endif
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>

@if($reports->isNotEmpty())
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtn = document.getElementById('editDataBtn');
    const modal = document.getElementById('editDataModal');
    const closeModal = document.getElementById('closeModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const form = document.getElementById('schoolDataForm');
    const saveButton = document.getElementById('saveData');
    const resetButton = document.getElementById('resetForm');
    
    // Mở modal
    if (editBtn && modal) {
        editBtn.addEventListener('click', function() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Ngăn scroll
        });
    }
    
    // Đóng modal
    function closeModalFunc() {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto'; // Khôi phục scroll
    }
    
    if (closeModal) {
        closeModal.addEventListener('click', closeModalFunc);
    }
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModalFunc);
    }
    
    // Đóng modal khi click outside
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModalFunc();
            }
        });
    }
    
    // Đóng modal bằng ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModalFunc();
        }
    });
    
    // Xử lý form
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

function saveSchoolData() {
    const form = document.getElementById('schoolDataForm');
    if (!form) return;
    
    const formData = new FormData(form);
    const saveButton = document.getElementById('saveData');
    const originalText = saveButton.innerHTML;
    
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang lưu...';
    saveButton.disabled = true;
    
    fetch('{{ route("front.primary-school-infrastructure.save-school-data") }}', {
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

// Xử lý modal nhập Excel
document.addEventListener('DOMContentLoaded', function() {
    const importBtn = document.getElementById('importExcelBtn');
    const importModal = document.getElementById('importExcelModal');
    const closeImportModal = document.getElementById('closeImportModal');
    const closeImportModalBtn = document.getElementById('closeImportModalBtn');
    const uploadExcelBtn = document.getElementById('uploadExcel');
    const importForm = document.getElementById('importExcelForm');
    
    // Mở modal nhập Excel
    if (importBtn) {
        importBtn.addEventListener('click', function() {
            // Lấy thông tin địa lý từ trang hiện tại
            updateImportGeoInfo();
            importModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    }
    
    // Đóng modal nhập Excel
    if (closeImportModal) {
        closeImportModal.addEventListener('click', function() {
            importModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }
    
    if (closeImportModalBtn) {
        closeImportModalBtn.addEventListener('click', function() {
            importModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
    }
    
    // Xử lý upload Excel
    if (uploadExcelBtn) {
        uploadExcelBtn.addEventListener('click', function() {
            uploadExcelFile();
        });
    }
    
    // Đóng modal khi click outside
    if (importModal) {
        importModal.addEventListener('click', function(e) {
            if (e.target === importModal) {
                importModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
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

function uploadExcelFile() {
    const form = document.getElementById('importExcelForm');
    const fileInput = document.getElementById('excelFile');
    const uploadBtn = document.getElementById('uploadExcel');
    
    if (!fileInput.files.length) {
        showNotification('Vui lòng chọn file Excel', 'error');
        return;
    }
    
    const formData = new FormData(form);
    const originalText = uploadBtn.innerHTML;
    
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang nhập...';
    uploadBtn.disabled = true;
    
    fetch('{{ route("front.primary-school-infrastructure.import-excel") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
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
            showNotification('Lỗi: ' + (data.message || 'Có lỗi xảy ra'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi nhập dữ liệu', 'error');
    })
    .finally(() => {
        uploadBtn.innerHTML = originalText;
        uploadBtn.disabled = false;
    });
}
</script>
@endif
@endsection
