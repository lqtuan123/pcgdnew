@extends('layouts.front')
@section('title','Báo cáo đội ngũ giáo viên mầm non')

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

/* CSS cho layout */
.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  padding: 12px 0;
}

.header h1 {
  font-size: 20px;
  font-weight: 600;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 8px;
}

.btn {
  padding: 8px 16px;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  display: inline-block;
  text-align: center;
  transition: all 0.2s;
}

.btn-info {
  background: #06b6d4;
  color: white;
}

.btn-info:hover {
  background: #0891b2;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover {
  background: #4b5563;
}

/* Form styles */
.form-container {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 12px;
  margin-bottom: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-size: 12px;
  color: #64748b;
  margin-bottom: 4px;
  font-weight: 500;
}

.form-group input,
.form-group select {
  padding: 8px 12px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-actions {
  display: flex;
  gap: 8px;
  align-items: flex-end;
}

/* Stats styles */
.stats-container {
  background: #f8fafc;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 20px;
}

.stats-title {
  font-weight: 600;
  margin-bottom: 12px;
  font-size: 16px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.stat-value {
  font-size: 24px;
  font-weight: bold;
  margin-bottom: 4px;
}

.stat-value.blue { color: #2563eb; }
.stat-value.green { color: #059669; }
.stat-value.purple { color: #7c3aed; }
.stat-value.orange { color: #ea580c; }

.stat-label {
  font-size: 14px;
  color: #64748b;
}

/* Table container */
.table-container {
  overflow-x: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: white;
}

/* Pagination */
.pagination-container {
  margin-top: 16px;
  display: flex;
  justify-content: center;
}

/* Utility classes */
.text-center { text-align: center; }
.text-left { text-align: left; }
.text-slate-500 { color: #64748b; }
.hover-bg-slate-50:hover { background-color: #f8fafc; }

.col-span-3 { grid-column: span 3; }
.col-span-4 { grid-column: span 4; }
.col-span-6 { grid-column: span 6; }
.col-span-12 { grid-column: span 12; }

.mb-1 { margin-bottom: 4px; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mb-6 { margin-bottom: 24px; }
.mt-4 { margin-top: 16px; }

.w-full { width: 100%; }
.flex { display: flex; }
.items-center { align-items: center; }
.items-end { align-items: flex-end; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 8px; }
.gap-3 { gap: 12px; }
.gap-4 { gap: 16px; }

@media (max-width: 768px) {
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .col-span-3,
  .col-span-4,
  .col-span-6,
  .col-span-12 {
    grid-column: span 1;
  }
  
  .header {
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
  }
  
  .header-actions {
    width: 100%;
    justify-content: flex-end;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
}
</style>

<div class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between py-3">
    <h1 class="text-xl font-semibold">Báo cáo đội ngũ giáo viên Mầm non - Cấp trường</h1>
    <div class="flex gap-2">
      @if(request()->has('year') && request('year'))
        <button type="button" id="importExcelBtn" 
                class="rounded-lg bg-green-600 px-3 py-2 text-white hover:bg-green-700">
          <i class="fas fa-upload mr-2"></i>Nhập Excel
        </button>
        <a href="{{ route('front.kindergarten-reports.export-excel', request()->query()) }}" 
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
    @if(str_contains($level, 'mầm non') && str_contains($level, 'tiểu học'))
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
        <a href="{{ route('front.kindergarten-reports.index') }}" class="btn-soft">Xoá lọc</a>
      </div>
    </div>
  </form>

  {{-- Thông báo chọn năm học --}}
  @if(!request()->has('year') || !request('year'))
    <div class="mb-4 rounded-lg bg-blue-50 p-4 border border-blue-200">
      <div class="flex items-center gap-2">
        <i class="fas fa-info-circle text-blue-600"></i>
        <span class="text-sm font-medium text-blue-800">
          Chọn năm học và nhấn Lọc để xem và chỉnh sửa dữ liệu giáo viên Mầm non
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

  {{-- Table --}}
  <div class="table-container">
    <table class="data-table">
      <thead>
        <tr>
          <th rowspan="3">TT</th>
          @if($level === 'province')
          <th rowspan="3">Tên xã/phường</th>
          @elseif($level === 'ward')
          <th rowspan="3">Trường/ Nhóm lớp ĐLTT</th>
          <th rowspan="3">Hạng trường</th>
          @endif
          <th colspan="10">CBQL, giáo viên, nhân viên</th>
          <th colspan="8">Giáo viên dạy MG 5 tuổi</th>
        </tr>
        <tr>
          <th rowspan="2">Tổng số</th>
          <th rowspan="2">Hợp<br> đồng<br> làm việc</th>
          <th colspan="2">Hợp đồng<br> lao động</th>
          <th colspan="2">CBQL</th>
          <th colspan="3">Giáo viên</th>
          <th colspan="1">Nhân<br> viên</th>

          <th rowspan="2">Tổng<br> số</th>
          <th rowspan="2">Hợp<br> đồng <br>làm việc</th>
          <th colspan="2">Hợp đồng <br>lao động</th>
          <th rowspan="2">Tỷ lệ<br> GV/Lớp</th>
          <th colspan="2">Trình độ đào tạo</th>
          <th rowspan="2">Đạt<br> chuẩn<br> nghề<br> nghiệp</th>
        </tr>
        <tr>
          <th>Tổng<br> số</th>
          <th>Được<br> hưởng <br>CĐ, CS<br> theo<br> quy định</th>
          <th>Tổng<br> số</th>
          <th>Phó <br>hiệu <br>trưởng</th>
          
          <th>Tổng<br> số</th>
          <th>Dân<br> tộc</th>
          <th>Tỷ lệ<br> GV/L</th>
          <th>Tổng<br> số</th>
          <th>Hợp<br> đồng</th>
          <th>Được<br> hưởng<br> CĐ, CS<br> theo <br>quy định</th>
          <th>Đạt<br> chuẩn</th>
          <th>Trên<br> chuẩn</th>
        </tr>
      </thead>
      <tbody>
        @forelse($reports as $index => $report)
        <tr class="hover-bg-slate-50">
          <td>{{ $reports->firstItem() + $index }}</td>
          @if($level === 'province')
          <td>{{ $report->ward_name ?? '' }}</td>
          @elseif($level === 'ward')
          <td>{{ $report->school_name ?? '' }}</td>
          <td>{{ $report->school_rank ?? 0 }}</td>
          @endif
          {{-- CBQL, giáo viên, nhân viên --}}
          @if($level === 'school')
          <td class="text-center"><span>{{ number_format($report->total_all ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->contract_work_all ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->contract_labor_all ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->cs_policy ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->total_cbql ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->vice_principal_cbql ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->total_teachers ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->ethnic_minority ?? 0) }}</span></td>
          <td class="text-center"><span>@formatNumber($report->teacher_ratio ?? 0, 2)</span></td>
          <td class="text-center"><span>{{ number_format($report->total_staff ?? 0) }}</span></td>
          {{-- Giáo viên dạy MG 5 tuổi --}}
          <td class="text-center"><span>{{ number_format($report->total_k5 ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->contract_work_k5 ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->contract_labor_k5 ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->cs_policy_k5 ?? 0) }}</span></td>
          <td class="text-center"><span>@formatNumber($report->teacher_ratio_k5 ?? 0, 2)</span></td>
          <td class="text-center"><span>{{ number_format($report->training_standard ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->training_above_standard ?? 0) }}</span></td>
          <td class="text-center"><span>{{ number_format($report->standard_profession ?? 0) }}</span></td>
          @else
          <td class="text-center">{{ number_format($report->total_all ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->contract_work_all ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->contract_labor_all ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->cs_policy ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->total_cbql ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->vice_principal_cbql ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->total_teachers ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->ethnic_minority ?? 0) }}</td>
          <td class="text-center">@formatNumber($report->teacher_ratio ?? 0, 2)</td>
          <td class="text-center">{{ number_format($report->total_staff ?? 0) }}</td>
          {{-- Giáo viên dạy MG 5 tuổi --}}
          <td class="text-center">{{ number_format($report->total_k5 ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->contract_work_k5 ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->contract_labor_k5 ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->cs_policy_k5 ?? 0) }}</td>
          <td class="text-center">@formatNumber($report->teacher_ratio_k5 ?? 0, 2)</td>
          <td class="text-center">{{ number_format($report->training_standard ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->training_above_standard ?? 0) }}</td>
          <td class="text-center">{{ number_format($report->standard_profession ?? 0) }}</td>
          @endif
        </tr>
        @empty
        <tr>
          <td colspan="20" class="text-center text-slate-500">
            @if($level === 'school')
              Vui lòng chọn trường Mầm non để nhập dữ liệu
            @else
              Không có dữ liệu trường Mầm non
            @endif
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Phân trang --}}
  @if(method_exists($reports, 'hasPages') && $reports->hasPages())
  <div class="mt-4">
    {{ $reports->links() }}
  </div>
  @endif
</div>

{{-- Modal form cho cấp trường --}}
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
          <h3 class="text-lg font-semibold text-gray-900">Chỉnh sửa dữ liệu báo cáo giáo viên Mầm non</h3>
          <button id="closeModal" class="text-gray-400 hover:text-gray-600">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <!-- Modal Body -->
        <form id="schoolDataForm" class="p-6">
          @csrf
          <input type="hidden" name="academic_year" value="{{ $year }}">
          <input type="hidden" name="school_id" value="{{ $geoIds['school'] ?? '' }}">
          <input type="hidden" name="ward_id" value="{{ $geoIds['ward'] ?? '' }}">
          
          {{-- Debug info --}}
          <div style="display: none;">
            Debug: year={{ $year }}, school_id={{ $geoIds['school'] ?? 'null' }}, ward_id={{ $geoIds['ward'] ?? 'null' }}
          </div>
          
          {{-- Form theo đúng thứ tự bảng --}}
          <div class="space-y-6">
            {{-- CBQL, giáo viên, nhân viên --}}
            <div>
              <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">CBQL, giáo viên, nhân viên</h4>
              
              {{-- Tổng số - Hợp đồng làm việc --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Tổng số - Hợp đồng làm việc</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tổng số</label>
                    <input type="number" name="total_all" value="{{ $report->total_all ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Hợp đồng làm việc</label>
                    <input type="number" name="contract_work_all" value="{{ $report->contract_work_all ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Hợp đồng lao động --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Hợp đồng lao động</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tổng số</label>
                    <input type="number" name="contract_labor_all" value="{{ $report->contract_labor_all ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Được hưởng CĐ, CS</label>
                    <input type="number" name="cs_policy" value="{{ $report->cs_policy ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- CBQL --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">CBQL</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tổng số</label>
                    <input type="number" name="total_cbql" value="{{ $report->total_cbql ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Phó hiệu trưởng</label>
                    <input type="number" name="vice_principal_cbql" value="{{ $report->vice_principal_cbql ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Giáo viên --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Giáo viên</h5>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tổng số</label>
                    <input type="number" name="total_teachers" value="{{ $report->total_teachers ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Dân tộc thiểu số</label>
                    <input type="number" name="ethnic_minority" value="{{ $report->ethnic_minority ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tỷ lệ GV/Lớp</label>
                    <input type="number" name="teacher_ratio" value="{{ $report->teacher_ratio ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Tổng số nhân viên --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Tổng số nhân viên</h5>
                <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tổng số</label>
                    <input type="number" name="total_staff" value="{{ $report->total_staff ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>
            </div>

            {{-- Giáo viên dạy MG 5 tuổi --}}
            <div>
              <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Giáo viên dạy MG 5 tuổi</h4>
              
              {{-- Tổng số - Hợp đồng làm việc --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Tổng số - Hợp đồng làm việc</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tổng số</label>
                    <input type="number" name="total_k5" value="{{ $report->total_k5 ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Hợp đồng làm việc</label>
                    <input type="number" name="contract_work_k5" value="{{ $report->contract_work_k5 ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Hợp đồng lao động --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Hợp đồng lao động</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Hợp đồng</label>
                    <input type="number" name="contract_labor_k5" value="{{ $report->contract_labor_k5 ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Được hưởng CĐ, CS</label>
                    <input type="number" name="cs_policy_k5" value="{{ $report->cs_policy_k5 ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Tỷ lệ GV/Lớp --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Tỷ lệ GV/Lớp</h5>
                <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tỷ lệ</label>
                    <input type="number" name="teacher_ratio_k5" value="{{ $report->teacher_ratio_k5 ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
                  </div>
                </div>
              </div>

              {{-- Trình độ đào tạo --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Trình độ đào tạo</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Đạt chuẩn</label>
                    <input type="number" name="training_standard" value="{{ $report->training_standard ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Trên chuẩn</label>
                    <input type="number" name="training_above_standard" value="{{ $report->training_above_standard ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>

              {{-- Đạt chuẩn nghề nghiệp --}}
              <div class="mb-4">
                <h5 class="text-sm font-medium text-gray-700 mb-2">Đạt chuẩn nghề nghiệp</h5>
                <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Đạt chuẩn</label>
                    <input type="number" name="standard_profession" value="{{ $report->standard_profession ?? 0 }}" 
                           class="w-full border border-gray-300 rounded px-3 py-2" min="0">
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex justify-end space-x-3 p-6 border-t">
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

  <script>
  document.addEventListener('DOMContentLoaded', function() {
      const editBtn = document.getElementById('editDataBtn');
      const modal = document.getElementById('editDataModal');
      const closeModal = document.getElementById('closeModal');
      const closeModalBtn = document.getElementById('closeModalBtn');
      const form = document.getElementById('schoolDataForm');
      const saveButton = document.getElementById('saveData');
      
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
  });

  function saveSchoolData() {
      const form = document.getElementById('schoolDataForm');
      const saveButton = document.getElementById('saveData');
      const originalText = saveButton.innerHTML;
      
      // Disable button và hiển thị loading
      saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Đang lưu...';
      saveButton.disabled = true;
      
      const formData = new FormData(form);
      
      fetch('{{ route("front.kindergarten-reports.save-school-data") }}', {
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
      
      // Thêm vào DOM
      document.body.appendChild(notification);
      
      // Tự động ẩn sau 3 giây
      setTimeout(() => {
          notification.remove();
      }, 3000);
  }
  </script>
@endif

@endsection

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
    
    fetch('{{ route("front.kindergarten-reports.import-excel") }}', {
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
    } catch (error) {
        console.log('Không thể lấy thông tin địa lý từ Alpine.js:', error);
    }
}
</script>