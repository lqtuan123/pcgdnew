@extends('layouts.front')

@section('title', 'Báo cáo cơ sở vật chất trường mầm non')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between py-3">
    <h1 class="text-xl font-semibold">Báo cáo cơ sở vật chất trường Mầm non</h1>
    <div class="flex gap-2">
      @if(request()->has('year') && request('year'))
        <button type="button" id="importExcelBtn" class="rounded-lg bg-green-600 px-3 py-2 text-white hover:bg-green-700">
          <i class="fas fa-upload mr-2"></i>Nhập Excel
        </button>
        <a href="{{ route('front.kindergarten-infrastructure.export-excel', request()->query()) }}" 
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
    @if(str_contains($level, 'mầm non') && (str_contains($level, 'tiểu học') || str_contains($level, 'thcs')))
      <div class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="fas fa-info-circle text-yellow-600"></i>
            <span class="text-sm text-yellow-800">Trường liên cấp: {{ $schoolLevel }}</span>
          </div>
          <div class="flex gap-2">
            @if(str_contains($level, 'tiểu học'))
              <a href="{{ route('front.elementary-infrastructure.index', request()->query()) }}" 
                 class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                <i class="fas fa-exchange-alt mr-1"></i>Chuyển sang Tiểu học
              </a>
            @endif
            @if(str_contains($level, 'thcs'))
              <a href="{{ route('front.primary-school-infrastructure.index', request()->query()) }}" 
                 class="px-3 py-1 bg-yellow-600 text-white text-sm rounded hover:bg-yellow-700">
                <i class="fas fa-exchange-alt mr-1"></i>Chuyển sang THCS
              </a>
            @endif
          </div>
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
        <a href="{{ route('front.kindergarten-infrastructure.index') }}" class="btn-soft">Xoá lọc</a>
      </div>
    </div>
  </form>


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

  {{-- Thông tin trường học --}}
  @if($reports->isNotEmpty())
    @php
      $report = $reports->first();
      $school = \App\Models\School::find($geoIds['school']);
    @endphp
    @if($school)
      <div class="mb-4 rounded-lg bg-blue-50 p-4 border border-blue-200">
        <div class="flex justify-between items-center mb-2">
          <h3 class="text-lg font-semibold text-blue-800">Thông tin trường Mầm non</h3>
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
            <input type="hidden" name="school_branch" value="{{ $report->school_branch ?? 0 }}">
            
            {{-- Form theo đúng thứ tự bảng --}}
            <div class="space-y-6">
              {{-- Điểm trường --}}
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Điểm trường</label>
                <input type="number" name="school_branch" value="{{ $report->school_branch ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>

              {{-- Tổng số phòng học --}}
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số phòng học</label>
                <input type="number" name="total_classrooms" value="{{ $report->total_classrooms ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>

              {{-- Số lớp, nhóm MG 5 tuổi --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Số lớp, nhóm MG 5 tuổi</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label>
                    <input type="number" name="total_k5_classes" value="{{ $report->total_k5_classes ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tr.đó, lớp ghép 5 tuổi</label>
                    <input type="number" name="mixed_k5_classes" value="{{ $report->mixed_k5_classes ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lớp học 2 buổi/ngày</label>
                    <input type="number" name="k5_double_shift_classes" value="{{ $report->k5_double_shift_classes ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Số lớp, nhóm dưới 5 tuổi</label>
                    <input type="number" name="under_5_classes" value="{{ $report->under_5_classes ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Phòng học cho lớp MG 5 tuổi --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Phòng học cho lớp MG 5 tuổi</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label>
                    <input type="number" name="total_k5_rooms" value="{{ $report->total_k5_rooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tỷ lệ P/L</label>
                    <input type="number" name="room_class_ratio" value="{{ $report->room_class_ratio ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Đủ bộ TBị, ĐD, ĐC TT</label>
                    <input type="number" name="fully_equipped_rooms" value="{{ $report->fully_equipped_rooms ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Phòng kiên cố --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Phòng kiên cố</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label>
                    <input type="number" name="solid_rooms_count" value="{{ $report->solid_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DTBQ phòng SHC (m2)</label>
                    <input type="number" name="solid_rooms_avg_area" value="{{ $report->solid_rooms_avg_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Phòng bán kiên cố --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Phòng bán kiên cố</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label>
                    <input type="number" name="semi_solid_rooms_count" value="{{ $report->semi_solid_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DTBQ phòng SHC (m2)</label>
                    <input type="number" name="semi_solid_rooms_avg_area" value="{{ $report->semi_solid_rooms_avg_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Phòng học khác (tạm, nhờ) --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Phòng học khác (tạm, nhờ)</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label>
                    <input type="number" name="temporary_rooms_count" value="{{ $report->temporary_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">DTBQ phòng SHC (m2)</label>
                    <input type="number" name="temporary_rooms_avg_area" value="{{ $report->temporary_rooms_avg_area ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Phòng vệ sinh và bếp ăn --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Phòng vệ sinh và bếp ăn</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phòng hoặc khu vệ sinh</label>
                    <input type="number" name="toilet_rooms_count" value="{{ $report->toilet_rooms_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">CT nước sạch</label>
                    <input type="number" name="clean_water_system" value="{{ $report->clean_water_system ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bếp ăn</label>
                    <input type="number" name="kitchen_count" value="{{ $report->kitchen_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Sân và đồ chơi --}}
              <div>
                <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Sân và đồ chơi</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sân chơi</label>
                    <input type="number" name="playground_count" value="{{ $report->playground_count ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tr đồ: Sân có ĐC</label>
                    <input type="number" name="equipped_playground_count" value="{{ $report->equipped_playground_count ?? 0 }}" 
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

      /* Màu sắc cho các nhóm cột */
      .group-lop {
        background-color: #fef3c7;
      }
      .group-phong {
        background-color: #dbeafe;
      }
      .group-ve-sinh {
        background-color: #dcfce7;
      }
      .group-sanchoi {
        background-color: #fecaca;
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

    <table class="data-table">
                                <thead>
                                    {{-- Hàng 1: nhóm chính --}}
                                    <tr>
                                        <th rowspan="3" class="sticky-col">TT</th>
                                        <th rowspan="3" class="sticky-col-2">Đ.tr</th>
                                        <th rowspan="3">Tổng số<br>phòng <br>học</th>
                                        <th class="group-lop" colspan="4">Số lớp, nhóm</th>
                                        <th class="group-phong" colspan="9">Phòng học cho lớp MG 5 tuổi</th>
                                        <th class="group-ve-sinh" colspan="1">Phòng<br> hoặc<br>khu vệ<br> sinh</th>
                                        <th class="group-ve-sinh" colspan="1">CT<br> nước<br>sạch</th>
                                        <th class="group-ve-sinh" colspan="1">Bếp ăn</th>
                                        <th class="group-sanchoi" colspan="2">Sân và đồ chơi</th>
                                    </tr>

                                    {{-- Hàng 2: nhóm con --}}
                                    <tr>
                                        <th class="group-lop" colspan="3">Số lớp MG 5 tuổi</th>
                                        <th class="group-lop" rowspan="2">Số lớp,<br> nhóm<br>dưới <br>5 tuổi</th>
                                        <th class="group-phong" rowspan="2">Tổng số</th>
                                        <th class="group-phong" rowspan="2">Tỷ lệ<br> P/L</th>
                                        <th class="group-phong" colspan="2">Kiên cố</th>
                                        <th class="group-phong" colspan="2">Bán<br> kiên cố</th>
                                        <th class="group-phong" colspan="2">Phòng học khác<br>(tạm, nhờ)</th>
                                        <th class="group-phong" rowspan="2">Đủ bộ<br> TBị,<br> ĐD, ĐC<br> TT</th>
                                        <th class="group-ve-sinh" rowspan="2">Số <br> lượng</th>
                                        <th class="group-ve-sinh" rowspan="2">Số <br> lượng</th>
                                        <th class="group-ve-sinh" rowspan="2">Số <br> lượng</th>
                                        <th class="group-sanchoi" colspan="1">Sân <br>chơi</th>
                                        <th class="group-sanchoi" colspan="1">Tr đồ:<br> Sân có<br> ĐC</th>
                                    </tr>

                                    {{-- Hàng 3: chi tiết chỉ số --}}
                                    <tr>
                                        <th class="group-lop">Tổng<br> số</th>
                                        <th class="group-lop">Tr.đó,<br> lớp<br>ghép<br> 5 tuổi</th>
                                        <th class="group-lop">Số lớp<br> học<br> 2 buổi/<br>ngày</th>
                                        <th class="group-phong">Tổng<br> số</th>
                                        <th class="group-phong">DTBQ<br> phòng<br>SHC <br>(m2)</th>
                                        <th class="group-phong">Tổng<br> số</th>
                                        <th class="group-phong">DTBQ<br> phòng<br>SHC <br>(m2)</th>
                                        <th class="group-phong">Tổng<br> số</th>
                                        <th class="group-phong">DTBQ<br> phòng<br>SHC<br> (m2)</th>
                                        <th class="group-sanchoi">Số <br> lượng</th>
                                        <th class="group-sanchoi">Số <br> lượng</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($reports as $index => $report)
                                    <tr class="hover-bg-slate-50">
                                        <td class="sticky-col">{{ $reports->firstItem() + $index }}</td>
                                        <td class="sticky-col-2 text-center"><span>{{ $report->school_branch ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->total_classrooms ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->total_k5_classes ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->mixed_k5_classes ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->k5_double_shift_classes ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->under_5_classes ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->total_k5_rooms ?? 0 }}</span></td>
                                        <td class="text-center"><span>@formatNumber($report->room_class_ratio ?? 0, 2)</span></td>
                                        <td class="text-center"><span>{{ $report->solid_rooms_count ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->solid_rooms_avg_area ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->semi_solid_rooms_count ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->semi_solid_rooms_avg_area ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->temporary_rooms_count ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->temporary_rooms_avg_area ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->fully_equipped_rooms ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->toilet_rooms_count ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->clean_water_system ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->kitchen_count ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->playground_count ?? 0 }}</span></td>
                                        <td class="text-center"><span>{{ $report->equipped_playground_count ?? 0 }}</span></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="21" class="text-center text-slate-500">
                                          Không có dữ liệu
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
    </table>
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
    
    fetch('{{ route("front.kindergarten-infrastructure.save-school-data") }}', {
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

// Import Excel Modal
document.addEventListener('DOMContentLoaded', function() {
    const importBtn = document.getElementById('importExcelBtn');
    const importModal = document.getElementById('importExcelModal');
    const closeImportModal = document.getElementById('closeImportModal');
    const cancelImportBtn = document.getElementById('cancelImportBtn');
    const importDataBtn = document.getElementById('importDataBtn');
    const importForm = document.getElementById('importExcelForm');

    if (importBtn) {
        importBtn.addEventListener('click', function() {
            updateImportGeoInfo();
            importModal.classList.remove('hidden');
        });
    }

    if (closeImportModal) {
        closeImportModal.addEventListener('click', function() {
            importModal.classList.add('hidden');
        });
    }

    if (cancelImportBtn) {
        cancelImportBtn.addEventListener('click', function() {
            importModal.classList.add('hidden');
        });
    }

    if (importDataBtn) {
        importDataBtn.addEventListener('click', function() {
            importExcelData();
        });
    }

    // Đóng modal khi click outside
    if (importModal) {
        importModal.addEventListener('click', function(e) {
            if (e.target === importModal) {
                importModal.classList.add('hidden');
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
    const fileInput = document.getElementById('excelFile');
    const importBtn = document.getElementById('importDataBtn');
    const importModal = document.getElementById('importExcelModal');
    
    if (!fileInput.files.length) {
        showNotification('Vui lòng chọn file Excel', 'error');
        return;
    }
    
    const formData = new FormData(form);
    
    // Thêm CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Disable button và hiển thị loading
    importBtn.disabled = true;
    importBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang xử lý...';
    
    fetch('{{ route("front.kindergarten-infrastructure.import-excel") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            importModal.classList.add('hidden');
            form.reset();
            // Reload trang để hiển thị dữ liệu mới
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Có lỗi xảy ra khi nhập dữ liệu', 'error');
    })
    .finally(() => {
        // Enable button và reset text
        importBtn.disabled = false;
        importBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Nhập dữ liệu';
    });
}

{{-- Import Excel Modal --}}
</script>

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
        
        {{-- Hidden inputs for geo info --}}
        <input type="hidden" name="geo[province_id]" value="{{ request('geo.province_id') }}">
        <input type="hidden" name="geo[ward_id]" value="{{ request('geo.ward_id') }}">
        <input type="hidden" name="geo[school_id]" value="{{ request('geo.school_id') }}">
        
        {{-- Geographical Information --}}
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

        {{-- Select Excel file --}}
        <div class="mb-6">
          <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Chọn file Excel</h4>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">File Excel (.xlsx, .xls)</label>
            <input type="file" name="excel_file" id="excelFile" accept=".xlsx,.xls" 
                   class="w-full border border-gray-300 rounded px-3 py-2" required>
            <p class="text-xs text-gray-500 mt-1">Chỉ chấp nhận file Excel (.xlsx, .xls). Dữ liệu sẽ được đọc từ dòng 10, cột D đến W.</p>
          </div>
        </div>

        {{-- Academic Year --}}
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

@endsection
