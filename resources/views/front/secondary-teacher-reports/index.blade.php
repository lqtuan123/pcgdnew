@extends('layouts.front')
@section('title','Báo cáo đội ngũ giáo viên THCS')

@section('content')
<style>
    /* CSS cho bảng dựa trên mẫu mau.blade.php */
    .data-table {
        border-collapse: collapse;
        width: 100%;
        min-width: 2000px;
        text-align: center;
        font-size: 13px;
    }

    .data-table th, 
    .data-table td {
        border: 1px solid #000;
        padding: 4px;
        white-space: nowrap;
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
        min-width: 100px;
        max-width: 100px;
    }

    /* Hover effect */
    tbody tr:hover {
        background-color: #f5f5f5;
    }

    /* Group styling */
    .group-don-vi {
        background-color: #e6f3ff;
    }

    .group-cbql {
        background-color: #fff2e6;
    }

    .group-giao-vien {
        background-color: #e6ffe6;
    }

    .group-trinh-do {
        background-color: #ffe6f2;
    }

    .group-chuyen-nganh {
        background-color: #f2e6ff;
    }

    .group-tpt {
        background-color: #e6f2ff;
    }

    .group-chuan {
        background-color: #fff2e6;
    }

    .group-nhan-vien {
        background-color: #f0f8ff;
    }

    /* Border cho đường kẻ đậm giữa các cột */
    .border-right-thick {
        border-right: 2px solid #34495e !important;
    }

    /* Subgroup styling */
    .subgroup-tong-quat {
        background-color: #f0fff0;
    }

    .subgroup-ty-le {
        background-color: #f5f5f5;
    }

    .subgroup-trinh-do {
        background-color: #ffe4e1;
    }

    .subgroup-chuyen-nganh {
        background-color: #f0e6ff;
    }

    .subgroup-chuan {
        background-color: #fff8dc;
    }

    /* Table container */
    .table-container {
        overflow-x: auto;
        overflow-y: visible;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: white;
        width: 100%;
    }
</style>

<div class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between py-3">
    <h1 class="text-xl font-semibold">Báo cáo đội ngũ giáo viên THCS
      @if($level === 'school')
        - Cấp trường
      @elseif($level === 'ward')
        - Cấp xã/phường
      @else
        - Cấp tỉnh/thành phố
      @endif
    </h1>
    <div class="flex gap-2">
      @if($level === 'school')
        <button id="importExcelBtn" class="rounded-lg bg-green-600 px-3 py-2 text-white hover:bg-green-700">
          <i class="fas fa-upload mr-2"></i>Nhập từ Excel
        </button>
        <a href="{{ route('front.secondary-teacher-reports.export-excel', request()->query()) }}" 
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
          <a href="{{ route('front.teacher-reports.index', request()->query()) }}" 
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
        <a href="{{ route('front.secondary-teacher-reports.index') }}" class="btn-soft">Xoá lọc</a>
      </div>
    </div>
  </form>

  {{-- Thông báo khi chưa chọn năm --}}
  @if(!request()->has('year') || !request('year'))
    <div class="mb-4 rounded-lg bg-blue-50 p-4 border border-blue-200">
      <div class="flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-600"></i>
        <span class="text-sm font-medium text-blue-800">
          Chọn năm học và nhấn Lọc để xem và chỉnh sửa dữ liệu
        </span>
      </div>
    </div>
  @endif


  {{-- Thông tin trường học --}}
  @if($geoIds['school'])
    @php
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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
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
          <div class="bg-white rounded px-3 py-2 border border-blue-200">
            <span class="text-sm text-blue-600 font-medium">Hạng trường:</span>
            <span class="text-sm text-blue-800 font-semibold">{{ $reportData['giao_vien']->school_rank ?? 'Chưa nhập' }}</span>
          </div>
          <div class="bg-white rounded px-3 py-2 border border-blue-200">
            <span class="text-sm text-blue-600 font-medium">Tỷ lệ GV/Lớp:</span>
            <span class="text-sm text-blue-800 font-semibold">{{ $reportData['giao_vien']->teacher_class_ratio ?? 'Chưa nhập' }}</span>
          </div>
        </div>
      </div>
    @endif
  @endif


  {{-- Table --}}
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th rowspan="3" class="group-don-vi sticky-col">TT</th>
          <th rowspan="3" class="group-don-vi sticky-col-2" style="min-width: 100px;">Đơn vị</th>
          <th rowspan="3" class="group-don-vi vertical">Tổng số</th>
          <th rowspan="3" class="group-don-vi vertical">Nữ</th>
          <th rowspan="3" class="group-don-vi vertical">Đảng viên</th>
          <th colspan="4" class="group-trinh-do">Trình độ chuyên môn</th>
          <th colspan="17" class="group-chuyen-nganh">Chuyên ngành đào tạo</th>
          <th rowspan="3" class="group-cbql vertical">P.Hiệu trưởng</th>
          <th rowspan="3" class="group-cbql vertical">Hiệu trưởng</th>
          <th rowspan="3" class="group-dan-toc vertical">Dân tộc</th>
          <th rowspan="3" class="group-tpt vertical">TPT.Đội</th>
          <th colspan="4" class="group-chuan">Chuẩn nghề nghiệp</th>
          <th colspan="4" class="group-nhan-vien">Nhân viên</th>
        </tr>
        <tr>
          <th rowspan="2" class="subgroup-trinh-do vertical">Trên đại học</th>
          <th rowspan="2" class="subgroup-trinh-do vertical">Đại học</th>
          <th rowspan="2" class="subgroup-trinh-do vertical">Cao đẳng</th>
          <th rowspan="2" class="subgroup-trinh-do vertical">Trung học</th>
          
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Toán</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Lý</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Hóa</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Sinh</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Văn</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Sử</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Địa</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">NN Anh</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">NN Pháp</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">NN Nga</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">NN khác</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">GDCD</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Công nghệ</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Thể dục</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Âm nhạc</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Mỹ thuật</th>
          <th rowspan="2" class="subgroup-chuyen-nganh vertical">Tin học</th>
          
          <th rowspan="2" class="subgroup-chuan vertical">Tốt</th>
          <th rowspan="2" class="subgroup-chuan vertical">Khá</th>
          <th rowspan="2" class="subgroup-chuan vertical">Đạt</th>
          <th rowspan="2" class="subgroup-chuan vertical">Chưa đạt</th>
          
          <th rowspan="2" class="group-nhan-vien vertical">Thư viện</th>
          <th rowspan="2" class="group-nhan-vien vertical">T.Bị - Thí nghiệm</th>
          <th rowspan="2" class="group-nhan-vien vertical">Văn phòng</th>
          <th rowspan="2" class="group-nhan-vien vertical">Y tế</th>
        </tr>
        <tr>
          {{-- Empty row for final header --}}
        </tr>
      </thead>
      
      <tbody>
        {{-- Dòng cố định: Trong biên chế --}}
        <tr class="hover-bg-slate-50">
          <td class="sticky-col">1</td>
          <td class="sticky-col-2 text-left font-weight-bold">Trong biên chế</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->total ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->female ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->party_member ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->above_university ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->university ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->college ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->secondary ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->mathematics ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->physics ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->chemistry ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->biology ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->literature ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->history ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->geography ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->english ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->french ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->russian ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->other_language ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->civic_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->technology ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->physical_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->music ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->art ?? '' }}</td>
          <td class="text-center">{{ $reportData['trong_bien_che']->informatics ?? '' }}</td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
        </tr>

        {{-- Dòng 2: TT=rỗng, Đơn vị=+ Quản lý --}}
        <tr class="hover-bg-slate-50">
          <td class="sticky-col"></td>
          <td class="sticky-col-2 text-left">+ Quản lý</td>
          <td class="text-center">{{ $reportData['quan_ly']->total ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->female ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->party_member ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->above_university ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->university ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->college ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->secondary ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->mathematics ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->physics ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->chemistry ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->biology ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->literature ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->history ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->geography ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->english ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->french ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->russian ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->other_language ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->civic_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->technology ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->physical_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->music ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->art ?? '' }}</td>
          <td class="text-center">{{ $reportData['quan_ly']->informatics ?? '' }}</td>
          <td class="text-center">{{ ($reportData['quan_ly'] && $reportData['quan_ly']->vice_principal && $reportData['quan_ly']->vice_principal != 0) ? $reportData['quan_ly']->vice_principal : '' }}</td>
          <td class="text-center">{{ ($reportData['quan_ly'] && $reportData['quan_ly']->principal && $reportData['quan_ly']->principal != 0) ? $reportData['quan_ly']->principal : '' }}</td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td> 
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
        </tr>

        {{-- Dòng 3: TT=rỗng, Đơn vị=+ Giáo viên --}}
        <tr class="hover-bg-slate-50">
          <td class="sticky-col"></td>
          <td class="sticky-col-2 text-left">+ Giáo viên</td>
          <td class="text-center">{{ $reportData['giao_vien']->total ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->female ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->party_member ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->above_university ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->university ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->college ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->secondary ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->mathematics ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->physics ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->chemistry ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->biology ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->literature ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->history ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->geography ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->english ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->french ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->russian ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->other_language ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->civic_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->technology ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->physical_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->music ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->art ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien']->informatics ?? '' }}</td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center">{{ ($reportData['giao_vien'] && $reportData['giao_vien']->ethnic && $reportData['giao_vien']->ethnic != 0) ? $reportData['giao_vien']->ethnic : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien'] && $reportData['giao_vien']->tpt_team && $reportData['giao_vien']->tpt_team != 0) ? $reportData['giao_vien']->tpt_team : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien'] && $reportData['giao_vien']->standard_good && $reportData['giao_vien']->standard_good != 0) ? $reportData['giao_vien']->standard_good : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien'] && $reportData['giao_vien']->standard_quite && $reportData['giao_vien']->standard_quite != 0) ? $reportData['giao_vien']->standard_quite : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien'] && $reportData['giao_vien']->standard_reach && $reportData['giao_vien']->standard_reach != 0) ? $reportData['giao_vien']->standard_reach : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien'] && $reportData['giao_vien']->standard_not_reach && $reportData['giao_vien']->standard_not_reach != 0) ? $reportData['giao_vien']->standard_not_reach : '' }}</td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
        </tr>

        {{-- Dòng 4: TT=rỗng, Đơn vị=+ Nhân viên --}}
        <tr class="hover-bg-slate-50">
          <td class="sticky-col"></td>
          <td class="sticky-col-2 text-left">+ Nhân viên</td>
          <td class="text-center">{{ $reportData['nhan_vien']->total ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->female ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->party_member ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->above_university ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->university ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->college ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->secondary ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->mathematics ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->physics ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->chemistry ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->biology ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->literature ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->history ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->geography ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->english ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->french ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->russian ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->other_language ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->civic_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->technology ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->physical_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->music ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->art ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhan_vien']->informatics ?? '' }}</td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center">{{ ($reportData['nhan_vien'] && $reportData['nhan_vien']->library_staff && $reportData['nhan_vien']->library_staff != 0) ? $reportData['nhan_vien']->library_staff : '' }}</td>
          <td class="text-center">{{ ($reportData['nhan_vien'] && $reportData['nhan_vien']->equipment_lab_staff && $reportData['nhan_vien']->equipment_lab_staff != 0) ? $reportData['nhan_vien']->equipment_lab_staff : '' }}</td>
          <td class="text-center">{{ ($reportData['nhan_vien'] && $reportData['nhan_vien']->office_staff && $reportData['nhan_vien']->office_staff != 0) ? $reportData['nhan_vien']->office_staff : '' }}</td>
          <td class="text-center">{{ ($reportData['nhan_vien'] && $reportData['nhan_vien']->medical_staff && $reportData['nhan_vien']->medical_staff != 0) ? $reportData['nhan_vien']->medical_staff : '' }}</td>
        </tr>

        {{-- Dòng cố định: Giáo viên hợp đồng --}}
        <tr class="hover-bg-slate-50">
          <td class="sticky-col">2</td>
          <td class="sticky-col-2 text-left font-weight-bold">Giáo viên hợp đồng</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->total ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->female ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->party_member ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->above_university ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->university ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->college ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->secondary ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->mathematics ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->physics ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->chemistry ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->biology ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->literature ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->history ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->geography ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->english ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->french ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->russian ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->other_language ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->civic_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->technology ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->physical_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->music ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->art ?? '' }}</td>
          <td class="text-center">{{ $reportData['giao_vien_hop_dong']->informatics ?? '' }}</td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center">{{ ($reportData['giao_vien_hop_dong'] && $reportData['giao_vien_hop_dong']->ethnic && $reportData['giao_vien_hop_dong']->ethnic != 0) ? $reportData['giao_vien_hop_dong']->ethnic : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien_hop_dong'] && $reportData['giao_vien_hop_dong']->tpt_team && $reportData['giao_vien_hop_dong']->tpt_team != 0) ? $reportData['giao_vien_hop_dong']->tpt_team : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien_hop_dong'] && $reportData['giao_vien_hop_dong']->standard_good && $reportData['giao_vien_hop_dong']->standard_good != 0) ? $reportData['giao_vien_hop_dong']->standard_good : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien_hop_dong'] && $reportData['giao_vien_hop_dong']->standard_quite && $reportData['giao_vien_hop_dong']->standard_quite != 0) ? $reportData['giao_vien_hop_dong']->standard_quite : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien_hop_dong'] && $reportData['giao_vien_hop_dong']->standard_reach && $reportData['giao_vien_hop_dong']->standard_reach != 0) ? $reportData['giao_vien_hop_dong']->standard_reach : '' }}</td>
          <td class="text-center">{{ ($reportData['giao_vien_hop_dong'] && $reportData['giao_vien_hop_dong']->standard_not_reach && $reportData['giao_vien_hop_dong']->standard_not_reach != 0) ? $reportData['giao_vien_hop_dong']->standard_not_reach : '' }}</td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
        </tr>

        {{-- Dòng cố định: Nhu cầu giáo viên --}}
        <tr class="hover-bg-slate-50">
          <td class="sticky-col">3</td>
          <td class="sticky-col-2 text-left font-weight-bold">Nhu cầu giáo viên</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->total ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->female ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->party_member ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->above_university ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->university ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->college ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->secondary ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->mathematics ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->physics ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->chemistry ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->biology ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->literature ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->history ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->geography ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->english ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->french ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->russian ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->other_language ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->civic_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->technology ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->physical_education ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->music ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->art ?? '' }}</td>
          <td class="text-center">{{ $reportData['nhu_cau_giao_vien']->informatics ?? '' }}</td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
          <td class="text-center bg-gray-200"></td>
        </tr>

      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  @if(method_exists($reports, 'hasPages') && $reports->hasPages())
    <div class="mt-4">
      {{ $reports->links() }}
    </div>
  @endif

  {{-- Popup Modal cho form nhập dữ liệu cấp trường --}}
  @if($geoIds['school'])
    @php
      $school = $geoIds['school'] ? \App\Models\School::find($geoIds['school']) : null;
    @endphp
    @if($school)
    <!-- Modal -->
    <div id="editDataModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
      <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
          <!-- Modal Header -->
          <div class="flex justify-between items-center p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-900">Chỉnh sửa dữ liệu báo cáo giáo viên THCS</h3>
            <button id="closeModal" class="text-gray-400 hover:text-gray-600">
              <i class="fas fa-times text-xl"></i>
            </button>
</div>

          <!-- Modal Body -->
          <form id="schoolDataForm" class="p-6">
            @csrf
            <input type="hidden" name="academic_year" value="{{ $year }}">
            <input type="hidden" name="school_id" value="{{ $school->id }}">
            <input type="hidden" name="ward_id" value="{{ $school->ward_id ?? $geoIds['ward'] }}">
            
            {{-- Form theo 6 vùng tương ứng với 6 dòng trong bảng --}}
            <div class="space-y-8">
              
              {{-- 1. QUẢN LÝ --}}
              <div class="bg-green-50 p-4 rounded-lg">
                <h4 class="font-semibold text-green-800 border-b border-green-200 pb-2 mb-4">
                  <i class="fas fa-user-tie mr-2"></i>1. + Quản lý
                </h4>
                
                {{-- Copy tất cả trường từ Trong biên chế --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label><input type="number" name="quan_ly[total]" value="{{ $reportData['quan_ly']->total ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Nữ</label><input type="number" name="quan_ly[female]" value="{{ $reportData['quan_ly']->female ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Đảng viên</label><input type="number" name="quan_ly[party_member]" value="{{ $reportData['quan_ly']->party_member ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
              </div>

                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Trình độ chuyên môn</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Trên ĐH</label><input type="number" name="quan_ly[above_university]" value="{{ $reportData['quan_ly']->above_university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">ĐH</label><input type="number" name="quan_ly[university]" value="{{ $reportData['quan_ly']->university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">CĐ</label><input type="number" name="quan_ly[college]" value="{{ $reportData['quan_ly']->college ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">TH</label><input type="number" name="quan_ly[secondary]" value="{{ $reportData['quan_ly']->secondary ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  </div>
                  
                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Môn học</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Toán</label><input type="number" name="quan_ly[mathematics]" value="{{ $reportData['quan_ly']->mathematics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Lý</label><input type="number" name="quan_ly[physics]" value="{{ $reportData['quan_ly']->physics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Hóa</label><input type="number" name="quan_ly[chemistry]" value="{{ $reportData['quan_ly']->chemistry ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sinh</label><input type="number" name="quan_ly[biology]" value="{{ $reportData['quan_ly']->biology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Văn</label><input type="number" name="quan_ly[literature]" value="{{ $reportData['quan_ly']->literature ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sử</label><input type="number" name="quan_ly[history]" value="{{ $reportData['quan_ly']->history ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Địa</label><input type="number" name="quan_ly[geography]" value="{{ $reportData['quan_ly']->geography ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Anh</label><input type="number" name="quan_ly[english]" value="{{ $reportData['quan_ly']->english ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Pháp</label><input type="number" name="quan_ly[french]" value="{{ $reportData['quan_ly']->french ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Nga</label><input type="number" name="quan_ly[russian]" value="{{ $reportData['quan_ly']->russian ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">NN khác</label><input type="number" name="quan_ly[other_language]" value="{{ $reportData['quan_ly']->other_language ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">GDCD</label><input type="number" name="quan_ly[civic_education]" value="{{ $reportData['quan_ly']->civic_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Công nghệ</label><input type="number" name="quan_ly[technology]" value="{{ $reportData['quan_ly']->technology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Thể dục</label><input type="number" name="quan_ly[physical_education]" value="{{ $reportData['quan_ly']->physical_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Âm nhạc</label><input type="number" name="quan_ly[music]" value="{{ $reportData['quan_ly']->music ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Mỹ thuật</label><input type="number" name="quan_ly[art]" value="{{ $reportData['quan_ly']->art ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Tin học</label><input type="number" name="quan_ly[informatics]" value="{{ $reportData['quan_ly']->informatics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  </div>
                  
                {{-- Thêm Hiệu trưởng và Phó Hiệu trưởng --}}
                  <div>
                  <h5 class="font-medium text-gray-700 mb-2">Cán bộ quản lý</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Hiệu trưởng</label>
                      <input type="number" name="quan_ly[principal]" value="{{ $reportData['quan_ly']->principal ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Phó Hiệu trưởng</label>
                      <input type="number" name="quan_ly[vice_principal]" value="{{ $reportData['quan_ly']->vice_principal ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                    </div>
                  </div>
                </div>
              </div>

              {{-- 2. GIÁO VIÊN --}}
              <div class="bg-yellow-50 p-4 rounded-lg">
                <h4 class="font-semibold text-yellow-800 border-b border-yellow-200 pb-2 mb-4">
                  <i class="fas fa-chalkboard-teacher mr-2"></i>2. + Giáo viên
                </h4>
                
                {{-- Copy tất cả trường từ Trong biên chế --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label><input type="number" name="giao_vien[total]" value="{{ $reportData['giao_vien']->total ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Nữ</label><input type="number" name="giao_vien[female]" value="{{ $reportData['giao_vien']->female ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Dân tộc</label><input type="number" name="giao_vien[ethnic]" value="{{ $reportData['giao_vien']->ethnic ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Đảng viên</label><input type="number" name="giao_vien[party_member]" value="{{ $reportData['giao_vien']->party_member ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  
                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Trình độ chuyên môn</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Trên ĐH</label><input type="number" name="giao_vien[above_university]" value="{{ $reportData['giao_vien']->above_university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">ĐH</label><input type="number" name="giao_vien[university]" value="{{ $reportData['giao_vien']->university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">CĐ</label><input type="number" name="giao_vien[college]" value="{{ $reportData['giao_vien']->college ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">TH</label><input type="number" name="giao_vien[secondary]" value="{{ $reportData['giao_vien']->secondary ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  </div>
                  
                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Môn học</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Toán</label><input type="number" name="giao_vien[mathematics]" value="{{ $reportData['giao_vien']->mathematics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Lý</label><input type="number" name="giao_vien[physics]" value="{{ $reportData['giao_vien']->physics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Hóa</label><input type="number" name="giao_vien[chemistry]" value="{{ $reportData['giao_vien']->chemistry ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sinh</label><input type="number" name="giao_vien[biology]" value="{{ $reportData['giao_vien']->biology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Văn</label><input type="number" name="giao_vien[literature]" value="{{ $reportData['giao_vien']->literature ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sử</label><input type="number" name="giao_vien[history]" value="{{ $reportData['giao_vien']->history ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Địa</label><input type="number" name="giao_vien[geography]" value="{{ $reportData['giao_vien']->geography ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Anh</label><input type="number" name="giao_vien[english]" value="{{ $reportData['giao_vien']->english ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Pháp</label><input type="number" name="giao_vien[french]" value="{{ $reportData['giao_vien']->french ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Nga</label><input type="number" name="giao_vien[russian]" value="{{ $reportData['giao_vien']->russian ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">NN khác</label><input type="number" name="giao_vien[other_language]" value="{{ $reportData['giao_vien']->other_language ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">GDCD</label><input type="number" name="giao_vien[civic_education]" value="{{ $reportData['giao_vien']->civic_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Công nghệ</label><input type="number" name="giao_vien[technology]" value="{{ $reportData['giao_vien']->technology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Thể dục</label><input type="number" name="giao_vien[physical_education]" value="{{ $reportData['giao_vien']->physical_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Âm nhạc</label><input type="number" name="giao_vien[music]" value="{{ $reportData['giao_vien']->music ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Mỹ thuật</label><input type="number" name="giao_vien[art]" value="{{ $reportData['giao_vien']->art ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Tin học</label><input type="number" name="giao_vien[informatics]" value="{{ $reportData['giao_vien']->informatics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  </div>
                  
                {{-- Thêm TPT đội và Chuẩn nghề nghiệp --}}
                  <div>
                  <h5 class="font-medium text-gray-700 mb-2">TPT đội & Chuẩn nghề nghiệp</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">TPT đội</label>
                      <input type="number" name="giao_vien[tpt_team]" value="{{ $reportData['giao_vien']->tpt_team ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Chuẩn tốt</label>
                      <input type="number" name="giao_vien[standard_good]" value="{{ $reportData['giao_vien']->standard_good ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Chuẩn khá</label>
                      <input type="number" name="giao_vien[standard_quite]" value="{{ $reportData['giao_vien']->standard_quite ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Chuẩn đạt</label>
                      <input type="number" name="giao_vien[standard_reach]" value="{{ $reportData['giao_vien']->standard_reach ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Chuẩn chưa đạt</label>
                      <input type="number" name="giao_vien[standard_not_reach]" value="{{ $reportData['giao_vien']->standard_not_reach ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                    </div>
                  </div>
                </div>
                  </div>
                  
              {{-- 3. NHÂN VIÊN --}}
              <div class="bg-purple-50 p-4 rounded-lg">
                <h4 class="font-semibold text-purple-800 border-b border-purple-200 pb-2 mb-4">
                  <i class="fas fa-user-cog mr-2"></i>3. + Nhân viên
                </h4>
                
                {{-- Copy tất cả trường từ Trong biên chế --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label><input type="number" name="nhan_vien[total]" value="{{ $reportData['nhan_vien']->total ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Nữ</label><input type="number" name="nhan_vien[female]" value="{{ $reportData['nhan_vien']->female ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Đảng viên</label><input type="number" name="nhan_vien[party_member]" value="{{ $reportData['nhan_vien']->party_member ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  
                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Trình độ chuyên môn</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Trên ĐH</label><input type="number" name="nhan_vien[above_university]" value="{{ $reportData['nhan_vien']->above_university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">ĐH</label><input type="number" name="nhan_vien[university]" value="{{ $reportData['nhan_vien']->university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">CĐ</label><input type="number" name="nhan_vien[college]" value="{{ $reportData['nhan_vien']->college ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">TH</label><input type="number" name="nhan_vien[secondary]" value="{{ $reportData['nhan_vien']->secondary ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  </div>
                  
                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Môn học</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Toán</label><input type="number" name="nhan_vien[mathematics]" value="{{ $reportData['nhan_vien']->mathematics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Lý</label><input type="number" name="nhan_vien[physics]" value="{{ $reportData['nhan_vien']->physics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Hóa</label><input type="number" name="nhan_vien[chemistry]" value="{{ $reportData['nhan_vien']->chemistry ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sinh</label><input type="number" name="nhan_vien[biology]" value="{{ $reportData['nhan_vien']->biology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Văn</label><input type="number" name="nhan_vien[literature]" value="{{ $reportData['nhan_vien']->literature ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sử</label><input type="number" name="nhan_vien[history]" value="{{ $reportData['nhan_vien']->history ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Địa</label><input type="number" name="nhan_vien[geography]" value="{{ $reportData['nhan_vien']->geography ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Anh</label><input type="number" name="nhan_vien[english]" value="{{ $reportData['nhan_vien']->english ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Pháp</label><input type="number" name="nhan_vien[french]" value="{{ $reportData['nhan_vien']->french ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Nga</label><input type="number" name="nhan_vien[russian]" value="{{ $reportData['nhan_vien']->russian ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">NN khác</label><input type="number" name="nhan_vien[other_language]" value="{{ $reportData['nhan_vien']->other_language ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">GDCD</label><input type="number" name="nhan_vien[civic_education]" value="{{ $reportData['nhan_vien']->civic_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Công nghệ</label><input type="number" name="nhan_vien[technology]" value="{{ $reportData['nhan_vien']->technology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Thể dục</label><input type="number" name="nhan_vien[physical_education]" value="{{ $reportData['nhan_vien']->physical_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Âm nhạc</label><input type="number" name="nhan_vien[music]" value="{{ $reportData['nhan_vien']->music ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Mỹ thuật</label><input type="number" name="nhan_vien[art]" value="{{ $reportData['nhan_vien']->art ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Tin học</label><input type="number" name="nhan_vien[informatics]" value="{{ $reportData['nhan_vien']->informatics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  </div>
                  
                {{-- Thêm các loại nhân viên --}}
                  <div>
                  <h5 class="font-medium text-gray-700 mb-2">Các loại nhân viên</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Thư viện</label>
                      <input type="number" name="nhan_vien[library_staff]" value="{{ $reportData['nhan_vien']->library_staff ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Thiết bị - Thí nghiệm</label>
                      <input type="number" name="nhan_vien[equipment_lab_staff]" value="{{ $reportData['nhan_vien']->equipment_lab_staff ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Văn phòng</label>
                      <input type="number" name="nhan_vien[office_staff]" value="{{ $reportData['nhan_vien']->office_staff ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Y tế</label>
                      <input type="number" name="nhan_vien[medical_staff]" value="{{ $reportData['nhan_vien']->medical_staff ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                    </div>
                  </div>
                </div>
                  </div>
                  
              {{-- 4. GIÁO VIÊN HỢP ĐỒNG --}}
              <div class="bg-orange-50 p-4 rounded-lg">
                <h4 class="font-semibold text-orange-800 border-b border-orange-200 pb-2 mb-4">
                  <i class="fas fa-file-contract mr-2"></i>4. Giáo viên hợp đồng
                </h4>
                
                {{-- Copy tất cả trường từ Trong biên chế --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label><input type="number" name="giao_vien_hop_dong[total]" value="{{ $reportData['giao_vien_hop_dong']->total ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Nữ</label><input type="number" name="giao_vien_hop_dong[female]" value="{{ $reportData['giao_vien_hop_dong']->female ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Dân tộc</label><input type="number" name="giao_vien_hop_dong[ethnic]" value="{{ $reportData['giao_vien_hop_dong']->ethnic ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Đảng viên</label><input type="number" name="giao_vien_hop_dong[party_member]" value="{{ $reportData['giao_vien_hop_dong']->party_member ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  
                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Trình độ chuyên môn</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Trên ĐH</label><input type="number" name="giao_vien_hop_dong[above_university]" value="{{ $reportData['giao_vien_hop_dong']->above_university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">ĐH</label><input type="number" name="giao_vien_hop_dong[university]" value="{{ $reportData['giao_vien_hop_dong']->university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">CĐ</label><input type="number" name="giao_vien_hop_dong[college]" value="{{ $reportData['giao_vien_hop_dong']->college ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">TH</label><input type="number" name="giao_vien_hop_dong[secondary]" value="{{ $reportData['giao_vien_hop_dong']->secondary ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                </div>

                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Môn học</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Toán</label><input type="number" name="giao_vien_hop_dong[mathematics]" value="{{ $reportData['giao_vien_hop_dong']->mathematics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Lý</label><input type="number" name="giao_vien_hop_dong[physics]" value="{{ $reportData['giao_vien_hop_dong']->physics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Hóa</label><input type="number" name="giao_vien_hop_dong[chemistry]" value="{{ $reportData['giao_vien_hop_dong']->chemistry ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sinh</label><input type="number" name="giao_vien_hop_dong[biology]" value="{{ $reportData['giao_vien_hop_dong']->biology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Văn</label><input type="number" name="giao_vien_hop_dong[literature]" value="{{ $reportData['giao_vien_hop_dong']->literature ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sử</label><input type="number" name="giao_vien_hop_dong[history]" value="{{ $reportData['giao_vien_hop_dong']->history ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Địa</label><input type="number" name="giao_vien_hop_dong[geography]" value="{{ $reportData['giao_vien_hop_dong']->geography ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Anh</label><input type="number" name="giao_vien_hop_dong[english]" value="{{ $reportData['giao_vien_hop_dong']->english ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Pháp</label><input type="number" name="giao_vien_hop_dong[french]" value="{{ $reportData['giao_vien_hop_dong']->french ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Nga</label><input type="number" name="giao_vien_hop_dong[russian]" value="{{ $reportData['giao_vien_hop_dong']->russian ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">NN khác</label><input type="number" name="giao_vien_hop_dong[other_language]" value="{{ $reportData['giao_vien_hop_dong']->other_language ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">GDCD</label><input type="number" name="giao_vien_hop_dong[civic_education]" value="{{ $reportData['giao_vien_hop_dong']->civic_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Công nghệ</label><input type="number" name="giao_vien_hop_dong[technology]" value="{{ $reportData['giao_vien_hop_dong']->technology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Thể dục</label><input type="number" name="giao_vien_hop_dong[physical_education]" value="{{ $reportData['giao_vien_hop_dong']->physical_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Âm nhạc</label><input type="number" name="giao_vien_hop_dong[music]" value="{{ $reportData['giao_vien_hop_dong']->music ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Mỹ thuật</label><input type="number" name="giao_vien_hop_dong[art]" value="{{ $reportData['giao_vien_hop_dong']->art ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Tin học</label><input type="number" name="giao_vien_hop_dong[informatics]" value="{{ $reportData['giao_vien_hop_dong']->informatics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                </div>
              </div> 

                {{-- Thêm TPT đội và Chuẩn nghề nghiệp (giống Giáo viên) --}}
              <div>
                  <h5 class="font-medium text-gray-700 mb-2">TPT đội & Chuẩn nghề nghiệp</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">TPT đội</label>
                      <input type="number" name="giao_vien_hop_dong[tpt_team]" value="{{ $reportData['giao_vien_hop_dong']->tpt_team ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
              <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Chuẩn tốt</label>
                      <input type="number" name="giao_vien_hop_dong[standard_good]" value="{{ $reportData['giao_vien_hop_dong']->standard_good ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Chuẩn khá</label>
                      <input type="number" name="giao_vien_hop_dong[standard_quite]" value="{{ $reportData['giao_vien_hop_dong']->standard_quite ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Chuẩn đạt</label>
                      <input type="number" name="giao_vien_hop_dong[standard_reach]" value="{{ $reportData['giao_vien_hop_dong']->standard_reach ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                      <label class="block text-sm font-medium text-gray-700 mb-1">Chuẩn chưa đạt</label>
                      <input type="number" name="giao_vien_hop_dong[standard_not_reach]" value="{{ $reportData['giao_vien_hop_dong']->standard_not_reach ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  </div>
                </div>
              </div>

              {{-- 5. NHU CẦU GIÁO VIÊN --}}
              <div class="bg-red-50 p-4 rounded-lg">
                <h4 class="font-semibold text-red-800 border-b border-red-200 pb-2 mb-4">
                  <i class="fas fa-user-plus mr-2"></i>5. Nhu cầu giáo viên
                </h4>
                
                {{-- Copy tất cả trường từ Trong biên chế (chỉ có các trường cơ bản) --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label><input type="number" name="nhu_cau_giao_vien[total]" value="{{ $reportData['nhu_cau_giao_vien']->total ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Nữ</label><input type="number" name="nhu_cau_giao_vien[female]" value="{{ $reportData['nhu_cau_giao_vien']->female ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  <div><label class="block text-sm font-medium text-gray-700 mb-1">Đảng viên</label><input type="number" name="nhu_cau_giao_vien[party_member]" value="{{ $reportData['nhu_cau_giao_vien']->party_member ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  
                <div class="mb-4">
                  <h5 class="font-medium text-gray-700 mb-2">Trình độ chuyên môn</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Trên ĐH</label><input type="number" name="nhu_cau_giao_vien[above_university]" value="{{ $reportData['nhu_cau_giao_vien']->above_university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">ĐH</label><input type="number" name="nhu_cau_giao_vien[university]" value="{{ $reportData['nhu_cau_giao_vien']->university ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">CĐ</label><input type="number" name="nhu_cau_giao_vien[college]" value="{{ $reportData['nhu_cau_giao_vien']->college ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">TH</label><input type="number" name="nhu_cau_giao_vien[secondary]" value="{{ $reportData['nhu_cau_giao_vien']->secondary ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                  </div>
                  
                  <div>
                  <h5 class="font-medium text-gray-700 mb-2">Môn học</h5>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Toán</label><input type="number" name="nhu_cau_giao_vien[mathematics]" value="{{ $reportData['nhu_cau_giao_vien']->mathematics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Lý</label><input type="number" name="nhu_cau_giao_vien[physics]" value="{{ $reportData['nhu_cau_giao_vien']->physics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Hóa</label><input type="number" name="nhu_cau_giao_vien[chemistry]" value="{{ $reportData['nhu_cau_giao_vien']->chemistry ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sinh</label><input type="number" name="nhu_cau_giao_vien[biology]" value="{{ $reportData['nhu_cau_giao_vien']->biology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Văn</label><input type="number" name="nhu_cau_giao_vien[literature]" value="{{ $reportData['nhu_cau_giao_vien']->literature ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Sử</label><input type="number" name="nhu_cau_giao_vien[history]" value="{{ $reportData['nhu_cau_giao_vien']->history ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Địa</label><input type="number" name="nhu_cau_giao_vien[geography]" value="{{ $reportData['nhu_cau_giao_vien']->geography ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Anh</label><input type="number" name="nhu_cau_giao_vien[english]" value="{{ $reportData['nhu_cau_giao_vien']->english ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Pháp</label><input type="number" name="nhu_cau_giao_vien[french]" value="{{ $reportData['nhu_cau_giao_vien']->french ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Nga</label><input type="number" name="nhu_cau_giao_vien[russian]" value="{{ $reportData['nhu_cau_giao_vien']->russian ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">NN khác</label><input type="number" name="nhu_cau_giao_vien[other_language]" value="{{ $reportData['nhu_cau_giao_vien']->other_language ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">GDCD</label><input type="number" name="nhu_cau_giao_vien[civic_education]" value="{{ $reportData['nhu_cau_giao_vien']->civic_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Công nghệ</label><input type="number" name="nhu_cau_giao_vien[technology]" value="{{ $reportData['nhu_cau_giao_vien']->technology ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Thể dục</label><input type="number" name="nhu_cau_giao_vien[physical_education]" value="{{ $reportData['nhu_cau_giao_vien']->physical_education ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Âm nhạc</label><input type="number" name="nhu_cau_giao_vien[music]" value="{{ $reportData['nhu_cau_giao_vien']->music ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Mỹ thuật</label><input type="number" name="nhu_cau_giao_vien[art]" value="{{ $reportData['nhu_cau_giao_vien']->art ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                    <div><label class="block text-sm font-medium text-gray-700 mb-1">Tin học</label><input type="number" name="nhu_cau_giao_vien[informatics]" value="{{ $reportData['nhu_cau_giao_vien']->informatics ?? 0 }}" class="w-full border border-gray-300 rounded px-3 py-2" min="0"></div>
                  </div>
                </div>
              </div>
                  </div>
                  
            {{-- Thông tin bổ sung --}}
            <div class="bg-gray-50 p-4 rounded-lg">
              <h4 class="font-semibold text-gray-800 border-b border-gray-200 pb-2 mb-4">
                <i class="fas fa-info-circle mr-2"></i>Thông tin bổ sung
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Hạng trường
                  </label>
                  <input type="number" name="school_rank" value="{{ $reportData['giao_vien']->school_rank ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  <p class="text-xs text-gray-500 mt-1">Nhập hạng trường (để trống = 0)</p>
                  </div>
                  <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Tỷ lệ GV/Lớp
                  </label>
                  <input type="number" name="teacher_class_ratio" value="{{ $reportData['giao_vien']->teacher_class_ratio ?? 0 }}" 
                         class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  <p class="text-xs text-gray-500 mt-1">Nhập tỷ lệ giáo viên/lớp (để trống = 0.00)</p>
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
@endif

@if($geoIds['school'])
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
    
    // Xử lý form - đã được xử lý ở cuối file
    
    // Xử lý reset form
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            if (confirm('Bạn có chắc chắn muốn làm mới form? Tất cả dữ liệu chưa lưu sẽ bị mất.')) {
                form.reset();
            }
        });
    }
});

// Function saveSchoolData cũ đã được thay thế bằng event listener mới

// Function showNotification cũ đã được thay thế bằng function mới ở cuối file

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
    
    fetch('{{ route("front.secondary-teacher-reports.import-excel") }}', {
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

// Tạo thông báo đẹp
function showNotification(message, type = 'success') {
    // Tạo element thông báo
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-md transform transition-all duration-300 ease-in-out`;
    
    if (type === 'success') {
        notification.className += ' bg-green-500 text-white';
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-3 text-xl"></i>
                <div>
                    <h4 class="font-semibold">Thành công!</h4>
                    <p class="text-sm">${message}</p>
                </div>
            </div>
        `;
    } else if (type === 'error') {
        notification.className += ' bg-red-500 text-white';
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-3 text-xl"></i>
                <div>
                    <h4 class="font-semibold">Lỗi!</h4>
                    <p class="text-sm">${message}</p>
                </div>
            </div>
        `;
    }
    
    // Thêm nút đóng
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '<i class="fas fa-times"></i>';
    closeBtn.className = 'absolute top-2 right-2 text-white hover:text-gray-200';
    closeBtn.onclick = () => notification.remove();
    notification.appendChild(closeBtn);
    
    // Thêm vào DOM
    document.body.appendChild(notification);
    
    // Auto remove sau 5 giây
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
    
    return notification;
}

// Validation cho các trường bắt buộc (có dấu *)
function validateRequiredFields() {
    // Hiện tại không có trường nào bắt buộc
    const requiredFields = [];
    
    const errors = [];
    
    requiredFields.forEach(field => {
        const input = document.querySelector(`input[name="${field.name}"]`);
        if (input) {
            const value = input.value.trim();
            if (!value || value === '' || value === '0') {
                errors.push(`${field.label} là bắt buộc`);
                input.classList.add('border-red-500');
            } else {
                input.classList.remove('border-red-500');
            }
        }
    });
    
    return errors;
}

// Xử lý submit form
document.getElementById('schoolDataForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Kiểm tra các trường bắt buộc
    const errors = validateRequiredFields();
    
    if (errors.length > 0) {
        showNotification('Vui lòng nhập đầy đủ các trường bắt buộc:\n' + errors.join('\n'), 'error');
        return false; // Ngăn không cho gửi form
    }
    
    // Nếu không có lỗi, gửi form
    const formData = new FormData(this);
    
    fetch('{{ route("front.secondary-teacher-reports.save-school-data") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Lưu dữ liệu thành công!', 'success');
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showNotification('Lỗi: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi lưu dữ liệu', 'error');
    });
});
</script>
@endif
@endif
</div>

@endsection