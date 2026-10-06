@extends('layouts.app')

@section('title', $pageTitle ?? 'Báo cáo')

@section('content')
<div class="container mx-auto px-4 py-6">
  {{-- Header --}}
  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ $pageTitle ?? 'Báo cáo' }}</h1>
    <p class="text-gray-600">{{ $pageDescription ?? 'Quản lý và xem báo cáo theo cấp tỉnh, xã, trường' }}</p>
  </div>

  {{-- Filters --}}
  @include('partials.front.user-geo-filter', [
    'geoIds' => $geoIds,
    'scheme' => $scheme,
    'year' => $year,
    'defaultYear' => $defaultYear,
    'q' => $q
  ])

  {{-- Statistics --}}
  @if($stats && $stats->isNotEmpty())
    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
      @foreach($stats as $wardId => $wardStats)
        <div class="bg-white rounded-lg shadow p-4">
          <h3 class="font-semibold text-gray-800 mb-2">Thống kê</h3>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-600">Tổng báo cáo:</span>
              <span class="font-medium">{{ $wardStats->sum('total_reports') }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Tổng GV:</span>
              <span class="font-medium">{{ number_format($wardStats->sum('total_teachers')) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Tổng lớp:</span>
              <span class="font-medium">{{ number_format($wardStats->sum('total_classes')) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-600">Tổng HS:</span>
              <span class="font-medium">{{ number_format($wardStats->sum('total_students')) }}</span>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  {{-- School Level Info --}}
  @if($level === 'school' && isset($reports) && $reports->count() > 0)
    @php $report = $reports->first(); @endphp
    <div class="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold text-blue-900">Thông tin trường học</h3>
          <div class="mt-2 text-sm text-blue-700">
            <p><strong>Tên trường:</strong> {{ $report->school->name ?? $report->school_name ?? '' }}</p>
            <p><strong>Mã trường:</strong> {{ $report->school->code ?? $report->school_code ?? '' }}</p>
            <p><strong>Năm học:</strong> {{ $report->academic_year ?? $year }}</p>
          </div>
        </div>
        <button id="editDataBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
          Chỉnh sửa dữ liệu
        </button>
      </div>
    </div>
  @endif

  {{-- Action Buttons --}}
  <div class="mb-4 flex flex-wrap gap-2">
    @if($level === 'school')
      <button id="editDataBtn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
        Chỉnh sửa dữ liệu
      </button>
    @else
      <a href="{{ route($routePrefix . '.export', request()->query()) }}" 
         class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
        Export Excel
      </a>
    @endif
  </div>

  {{-- Table --}}
  <div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          {{-- Main Header Row --}}
          <tr>
            <th class="group-don-vi vertical" rowspan="2">
              @if($level === 'province')
                Tên xã/phường
              @else
                Tên trường
              @endif
            </th>
            <th class="group-don-vi vertical" rowspan="2">Mã trường</th>
            @if($level === 'ward')
              <th class="group-don-vi vertical" rowspan="2">Hạng trường</th>
            @endif
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
          
          {{-- Sub Header Row --}}
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
        
        <tbody class="bg-white divide-y divide-gray-200">
          @forelse($reports as $index => $report)
            <tr @if($level === 'school') id="schoolDataRow" @elseif(($report->school_name ?? '') === 'TỔNG CỘNG' || ($report->ward_name ?? '') === 'TỔNG CỘNG') class="font-bold bg-yellow-50" @endif>
              {{-- Đơn vị --}}
              <td class="sticky-col">
                @if($level === 'province')
                  {{ $report->ward_name ?? $report->ward?->name ?? '' }}
                @else
                  {{ $report->school_name ?? '' }}
                @endif
              </td>
              <td>{{ $report->school_code ?? '' }}</td>
              
              @if($level === 'ward')
                <td>{{ $report->school_rank ?? '' }}</td>
              @endif

              {{-- Dữ liệu báo cáo --}}
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->sessions_per_day ?? 0 }}</span>
                @else
                  {{ $report->sessions_per_day ?? '' }}
                @endif
              </td>
              
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->principal ?? 0 }}</span>
                @else
                  {{ $report->principal ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->vice_principal ?? 0 }}</span>
                @else
                  {{ $report->vice_principal ?? '' }}
                @endif
              </td>
              
              {{-- Giáo viên - Tổng quát --}}
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->total_teachers ?? 0 }}</span>
                @else
                  {{ $report->total_teachers ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->teacher_quota ?? 0 }}</span>
                @else
                  {{ $report->teacher_quota ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->teacher_contract ?? 0 }}</span>
                @else
                  {{ $report->teacher_contract ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->female_teachers ?? 0 }}</span>
                @else
                  {{ $report->female_teachers ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->ethnic_teachers ?? 0 }}</span>
                @else
                  {{ $report->ethnic_teachers ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ number_format($report->teacher_student_ratio ?? 0, 2) }}</span>
                @else
                  {{ number_format($report->teacher_student_ratio ?? 0, 2) }}
                @endif
              </td>

              {{-- Trình độ đào tạo --}}
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_above_uni ?? 0 }}</span>
                @else
                  {{ $report->training_above_uni ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_uni ?? 0 }}</span>
                @else
                  {{ $report->training_uni ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_college ?? 0 }}</span>
                @else
                  {{ $report->training_college ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_ped ?? 0 }}</span>
                @else
                  {{ $report->training_ped ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_below_ped ?? 0 }}</span>
                @else
                  {{ $report->training_below_ped ?? '' }}
                @endif
              </td>

              {{-- Loại hình đào tạo --}}
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_primary ?? 0 }}</span>
                @else
                  {{ $report->training_primary ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_an ?? 0 }}</span>
                @else
                  {{ $report->training_an ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_mt ?? 0 }}</span>
                @else
                  {{ $report->training_mt ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_td ?? 0 }}</span>
                @else
                  {{ $report->training_td ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_informatics ?? 0 }}</span>
                @else
                  {{ $report->training_informatics ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_foreign_lang ?? 0 }}</span>
                @else
                  {{ $report->training_foreign_lang ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->training_other ?? 0 }}</span>
                @else
                  {{ $report->training_other ?? '' }}
                @endif
              </td>

              {{-- Chuẩn nghề nghiệp --}}
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->standard_good ?? 0 }}</span>
                @else
                  {{ $report->standard_good ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->standard_quite ?? 0 }}</span>
                @else
                  {{ $report->standard_quite ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->standard_reach ?? 0 }}</span>
                @else
                  {{ $report->standard_reach ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->standard_not_reach ?? 0 }}</span>
                @else
                  {{ $report->standard_not_reach ?? '' }}
                @endif
              </td>

              {{-- Nhân viên --}}
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->tpt_team ?? 0 }}</span>
                @else
                  {{ $report->tpt_team ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->office_staff ?? 0 }}</span>
                @else
                  {{ $report->office_staff ?? '' }}
                @endif
              </td>
              <td>
                @if($level === 'school')
                  <span class="text-center">{{ $report->library_other_staff ?? 0 }}</span>
                @else
                  {{ $report->library_other_staff ?? '' }}
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ $level === 'province' ? 25 : ($level === 'ward' ? 26 : 24) }}" class="px-6 py-4 text-center text-gray-500">
                Không có dữ liệu
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Pagination --}}
  @if(method_exists($reports, 'hasPages') && $reports->hasPages())
    <div class="mt-4">
      {{ $reports->links() }}
    </div>
  @endif
</div>

{{-- Modal cho cấp trường --}}
@if($level === 'school')
  @include('front.reports.modals.school-data-form', [
    'report' => $reports->first() ?? new $modelClass,
    'year' => $year,
    'routePrefix' => $routePrefix
  ])
@endif

{{-- JavaScript --}}
@if($level === 'school')
  @include('front.reports.scripts.school-level')
@endif
@endsection
