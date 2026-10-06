@extends('layouts.front')
@section('title','Báo cáo đội ngũ giáo viên')

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

    /* Màu sắc cho các nhóm cột */
    .group-don-vi {
        background-color: #fff2cc;
    }

    .group-cbql {
        background-color: #dae8fc;
    }

    .group-giao-vien {
        background-color: #d5e8d4;
    }

    .group-nhan-vien {
        background-color: #f8cecc;
    }

    /* Màu sắc cho các nhóm con */
    .subgroup-tong-quat {
        background-color: #e1f5fe;
    }

    .subgroup-trinh-do {
        background-color: #f3e5f5;
    }

    .subgroup-loai-hinh {
        background-color: #e8f5e8;
    }

    .subgroup-chuan {
        background-color: #fff3e0;
    }

    /* Sticky column cho cột TT */
    .sticky-col {
        position: sticky;
        left: 0;
        z-index: 10;
        background: white;
    }
    
    .sticky-col-2 {
        position: sticky;
        left: 30px;
        z-index: 9;
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
    <h1 class="text-xl font-semibold">Báo cáo đội ngũ giáo viên Tiểu học - Cấp trường</h1>
    <div class="flex gap-2">
      @if(request()->has('year') && request('year'))
        <button type="button" id="importExcelBtn" 
                class="rounded-lg bg-green-600 px-3 py-2 text-white hover:bg-green-700">
          <i class="fas fa-upload mr-2"></i>Nhập Excel
        </button>
        <a href="{{ route('front.teacher-reports.export-excel', request()->query()) }}" 
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
          <a href="{{ route('front.secondary-teacher-reports.index', request()->query()) }}" 
             class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
            <i class="fas fa-exchange-alt mr-1"></i>Chuyển sang THCS
          </a>
        </div>
      </div>
    @endif
  @endif

  <form method="get" class="mb-6" id="filterForm">

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
            @php $academicYear = $i . '-' . ($i + 1); @endphp
            <option value="{{ $academicYear }}" @selected(request('year') == $academicYear)>{{ $academicYear }}</option>
          @endfor
        </select>
      </div>

      <div class="md:col-span-9 flex items-end gap-2">
        <button class="btn-primary">Lọc</button>
        <a href="{{ route('front.teacher-reports.index') }}" class="btn-soft">Xoá lọc</a>
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

  {{-- Popup Modal cho form nhập dữ liệu --}}
  @if($reports->isNotEmpty())
    @php
      $report = $reports->first();
    @endphp
    <!-- Modal -->
    <div id="editDataModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
      <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Chỉnh sửa dữ liệu báo cáo</h3>
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
            
            {{-- Form theo đúng thứ tự bảng --}}
            <div class="space-y-6">
              {{-- Hạng trường --}}
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Hạng trường</label>
                <input type="number" name="school_rank" value="{{ $report->school_rank ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>

              {{-- 2 buổi/ngày --}}
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">2 buổi/ngày</label>
                <input type="number" name="sessions_per_day" value="{{ $report->sessions_per_day ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>

              {{-- CBQL --}}
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">HT (Hiệu trưởng)</label>
                  <input type="number" name="principal" value="{{ $report->principal ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Phó HT</label>
                  <input type="number" name="vice_principal" value="{{ $report->vice_principal ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                </div>
              </div>

              {{-- Giáo viên - Tổng quát --}}
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label>
                  <input type="number" name="total_teachers" value="{{ $report->total_teachers ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Biên chế</label>
                  <input type="number" name="teacher_quota" value="{{ $report->teacher_quota ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Hợp đồng</label>
                  <input type="number" name="teacher_contract" value="{{ $report->teacher_contract ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Nữ</label>
                  <input type="number" name="female_teachers" value="{{ $report->female_teachers ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Dân tộc</label>
                  <input type="number" name="ethnic_teachers" value="{{ $report->ethnic_teachers ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                </div>
              </div>

              {{-- Tỷ lệ GV/L --}}
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tỷ lệ GV/L</label>
                <input type="number" name="teacher_student_ratio" value="{{ $report->teacher_student_ratio ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
              </div>

              {{-- Trình độ đào tạo --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Trình độ đào tạo</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trên ĐH</label>
                    <input type="number" name="training_above_uni" value="{{ $report->training_above_uni ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ĐH</label>
                    <input type="number" name="training_uni" value="{{ $report->training_uni ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CĐ</label>
                    <input type="number" name="training_college" value="{{ $report->training_college ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">THSP</label>
                    <input type="number" name="training_ped" value="{{ $report->training_ped ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Dưới THSP</label>
                    <input type="number" name="training_below_ped" value="{{ $report->training_below_ped ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Loại hình đào tạo --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Loại hình đào tạo</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tiểu học</label>
                    <input type="number" name="training_primary" value="{{ $report->training_primary ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">AN</label>
                    <input type="number" name="training_an" value="{{ $report->training_an ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">MT</label>
                    <input type="number" name="training_mt" value="{{ $report->training_mt ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">TD</label>
                    <input type="number" name="training_td" value="{{ $report->training_td ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tin học</label>
                    <input type="number" name="training_informatics" value="{{ $report->training_informatics ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">NN</label>
                    <input type="number" name="training_foreign_lang" value="{{ $report->training_foreign_lang ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Khác</label>
                    <input type="number" name="training_other" value="{{ $report->training_other ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Chuẩn nghề nghiệp --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Chuẩn nghề nghiệp</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tốt</label>
                    <input type="number" name="standard_good" value="{{ $report->standard_good ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Khá</label>
                    <input type="number" name="standard_quite" value="{{ $report->standard_quite ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Đạt</label>
                    <input type="number" name="standard_reach" value="{{ $report->standard_reach ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Chưa đạt</label>
                    <input type="number" name="standard_not_reach" value="{{ $report->standard_not_reach ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- TPT Đội --}}
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">TPT Đội</label>
                <input type="number" name="tpt_team" value="{{ $report->tpt_team ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>

              {{-- Nhân viên --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Nhân viên</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Văn phòng</label>
                    <input type="number" name="office_staff" value="{{ $report->office_staff ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">T.Viện - TBDH</label>
                    <input type="number" name="library_other_staff" value="{{ $report->library_other_staff ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
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


  {{-- Thông báo khi chưa chọn năm hoặc không có dữ liệu --}}
  @if(!request()->has('year') || !request('year'))
    <div class="mb-4 rounded-lg bg-blue-50 p-4 border border-blue-200">
      <div class="flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-600"></i>
        <span class="text-sm font-medium text-blue-800">
          Chọn năm học và nhấn Lọc để xem và chỉnh sửa dữ liệu giáo viên Tiểu học
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
          <th rowspan="3">TT</th>
          <th class="group-don-vi" colspan="2">Đơn vị</th>
          <th class="group-cbql" colspan="2">CBQL</th>
          <th class="group-giao-vien" colspan="23">Giáo viên</th>
          <th class="group-nhan-vien" colspan="2">Nhân viên</th>
        </tr>

        {{-- Hàng 2: tiêu đề nhóm con --}}
        <tr>
          <th class="group-don-vi vertical sticky-col-2" rowspan="2">Hạng trường</th>
          <th class="group-don-vi vertical" rowspan="2">2 buổi/ngày</th>
          <th class="group-cbql vertical" rowspan="2">HT</th>
          <th class="group-cbql vertical" rowspan="2">Phó HT</th>
          <th class="subgroup-tong-quat vertical" rowspan="2">Tổng số</th>
          <th class="subgroup-tong-quat vertical" rowspan="2">Biên chế</th>
          <th class="subgroup-tong-quat vertical" rowspan="2">Hợp đồng</th>
          <th class="subgroup-tong-quat vertical" rowspan="2">Nữ</th>
          <th class="subgroup-tong-quat vertical" rowspan="2">Dân tộc</th>
          <th class="subgroup-tong-quat vertical" rowspan="2">Tỷ lệ GV/L</th>
          <th class="subgroup-trinh-do" colspan="5">Trình độ đào tạo</th>
          <th class="subgroup-loai-hinh" colspan="7">Loại hình đào tạo</th>
          <th class="subgroup-chuan" colspan="4">Chuẩn nghề nghiệp</th>
          <th class="group-giao-vien vertical" rowspan="2">TPT Đội</th>
          <th class="group-nhan-vien vertical" rowspan="2">Văn phòng</th>
          <th class="group-nhan-vien vertical" rowspan="2">T.Viện - TBDH</th>
        </tr>

        {{-- Hàng 3: tiêu đề cột chi tiết --}}
        <tr>
          {{-- Trình độ đào tạo --}}
          <th class="subgroup-trinh-do vertical">Trên ĐH</th>
          <th class="subgroup-trinh-do vertical">ĐH</th>
          <th class="subgroup-trinh-do vertical">CĐ</th>
          <th class="subgroup-trinh-do vertical">THSP</th>
          <th class="subgroup-trinh-do vertical">Dưới THSP</th>

          {{-- Loại hình đào tạo --}}
          <th class="subgroup-loai-hinh vertical">Tiểu học</th>
          <th class="subgroup-loai-hinh vertical">AN</th>
          <th class="subgroup-loai-hinh vertical">MT</th>
          <th class="subgroup-loai-hinh vertical">TD</th>
          <th class="subgroup-loai-hinh vertical">Tin học</th>
          <th class="subgroup-loai-hinh vertical">NN</th>
          <th class="subgroup-loai-hinh">Khác</th>

          {{-- Chuẩn nghề nghiệp --}}
          <th class="subgroup-chuan">Tốt</th>
          <th class="subgroup-chuan">Khá</th>
          <th class="subgroup-chuan">Đạt</th>
          <th class="subgroup-chuan vertical">Chưa đạt</th>
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
            
            {{-- Đơn vị --}}
            <td class="sticky-col-2 text-center">{{ $report->school_rank ?? '' }}</td>
            <td class="text-center">
              <span>{{ $report->sessions_per_day ?? 0 }}</span>
            </td>
            
            {{-- CBQL --}}
            <td class="text-center">
              <span>{{ $report->principal ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->vice_principal ?? 0 }}</span>
            </td>
            
            {{-- Giáo viên - Tổng quát --}}
            <td class="text-center">
              <span>{{ $report->total_teachers ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->teacher_quota ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->teacher_contract ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->female_teachers ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->ethnic_teachers ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>@formatNumber($report->teacher_student_ratio ?? 0, 2)</span>
            </td>
            
            {{-- Trình độ đào tạo --}}
            <td class="text-center">
              <span>{{ $report->training_above_uni ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_uni ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_college ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_ped ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_below_ped ?? 0 }}</span>
            </td>
            
            {{-- Loại hình đào tạo --}}
            <td class="text-center">
              <span>{{ $report->training_primary ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_an ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_mt ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_td ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_informatics ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_foreign_lang ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->training_other ?? 0 }}</span>
            </td>
            
            {{-- Chuẩn nghề nghiệp --}}
            <td class="text-center">
              <span>{{ $report->standard_good ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->standard_quite ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->standard_reach ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->standard_not_reach ?? 0 }}</span>
            </td>
            
            {{-- TPT Đội --}}
            <td class="text-center">
              <span>{{ $report->tpt_team ?? 0 }}</span>
            </td>
            
            {{-- Nhân viên --}}
            <td class="text-center">
              <span>{{ $report->office_staff ?? 0 }}</span>
            </td>
            <td class="text-center">
              <span>{{ $report->library_other_staff ?? 0 }}</span>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="30" class="text-center text-slate-500">
              Vui lòng chọn trường Tiểu học để nhập dữ liệu
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
            @if($level === 'province')
              {{ $report->ward_name ?? $report->ward?->name ?? '' }}
            @else
              {{ $report->school_name ?? '' }}
            @endif
          </h3>
          <span class="text-sm text-slate-500">{{ $report->academic_year }}</span>
        </div>
        
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div>
            <span class="text-slate-600">Hạng trường:</span>
            <span class="font-medium">{{ $report->school_rank ?? '' }}</span>
          </div>
          <div>
            <span class="text-slate-600">2 buổi/ngày:</span>
            <span class="font-medium">{{ $report->sessions_per_day ?? '' }}</span>
          </div>
          <div>
            <span class="text-slate-600">HT:</span>
            <span class="font-medium">{{ $report->principal ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Phó HT:</span>
            <span class="font-medium">{{ $report->vice_principal ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Tổng GV:</span>
            <span class="font-medium">{{ $report->total_teachers ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Nữ:</span>
            <span class="font-medium">{{ $report->female_teachers ?? 0 }}</span>
          </div>
          <div>
            <span class="text-slate-600">Tỷ lệ GV/L:</span>
            <span class="font-medium">@formatNumber($report->teacher_student_ratio ?? 0, 2)</span>
          </div>
          <div>
            <span class="text-slate-600">Đạt chuẩn:</span>
            <span class="font-medium">{{ ($report->standard_good ?? 0) + ($report->standard_quite ?? 0) + ($report->standard_reach ?? 0) }}</span>
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
    
    fetch('{{ route("front.teacher-reports.save-school-data") }}', {
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

// Import Excel Modal và JavaScript
</script>

<!-- Import Excel Modal -->
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
              @php $academicYear = $i . '-' . ($i + 1); @endphp
              <option value="{{ $academicYear }}" @selected(request('year') == $academicYear)>{{ $academicYear }}</option>
            @endfor
          </select>
          <p class="text-xs text-gray-500 mt-1">Chọn năm học muốn nhập dữ liệu</p>
        </div>

        <!-- Modal Footer -->
        <div class="flex justify-end space-x-3 p-6 border-t">
          <button type="button" id="closeImportModalBtn" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
            Hủy
          </button>
          <button type="button" id="uploadExcel" class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700">
            <i class="fas fa-upload mr-2"></i>Nhập dữ liệu
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
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
    function closeImportModalFunc() {
        importModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
        importForm.reset();
    }
    
    if (closeImportModal) {
        closeImportModal.addEventListener('click', closeImportModalFunc);
    }
    
    if (closeImportModalBtn) {
        closeImportModalBtn.addEventListener('click', closeImportModalFunc);
    }
    
    // Xử lý upload Excel
    if (uploadExcelBtn) {
        uploadExcelBtn.addEventListener('click', function() {
            uploadExcelFile();
        });
    }
});

// Hàm upload Excel file
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
    
    fetch('{{ route("front.teacher-reports.import-excel") }}', {
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
            showNotification('Lỗi: ' + (data.message || ''), 'error');
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

// Cập nhật thông tin địa lý trong modal nhập Excel
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
        console.error('Lỗi khi cập nhật thông tin địa lý:', error);
    }
}
</script>

@endsection
