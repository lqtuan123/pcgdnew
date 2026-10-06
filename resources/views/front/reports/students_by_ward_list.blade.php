@extends('layouts.front')
@section('title','Danh sách HS theo phường/xã')
 
@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">

  {{-- Bộ lọc nhanh trong trang danh sách --}}
  <form method="GET" action="{{ route('front.reports.searchreport') }}" class="mb-4 grid gap-3 md:grid-cols-12">
     @php
      $base = \App\Models\SurveyForm::currentAcademicYear();
      [$y1,$y2] = explode('-', $base);
      $years = [($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1)];
    @endphp
    <input type="hidden" name="scheme" value="{{ $scheme }}">
    <input type="hidden" name="geo[province_id]" value="{{ $geo['province_id'] ?? '' }}">
    <input type="hidden" name="geo[district_id]" value="{{ $geo['district_id'] ?? '' }}">
    <input type="hidden" name="geo[ward_id]" value="{{ $geo['ward_id'] ?? '' }}">
    <input type="hidden" name="geo[village_id]" value="{{ $geo['village_id'] ?? '' }}">
    <input type="hidden" name="report_group_id" value="{{ $report_group_id ?? '' }}">
    <input type="hidden" name="report_detail_id" value="{{ $report_detail_id ?? '' }}">

    <div class="md:col-span-3">
      <label class="block text-xs text-slate-500 mb-1">Năm học</label>
      <select name="year" class="w-full rounded-lg border px-3 py-2">
        <option value="all" @selected($year==='all')>— Tất cả —</option>
        @foreach($years as $yy)
          <option value="{{ $yy }}" @selected($yy==$year)>{{ $yy }}</option>
        @endforeach
      </select>
    </div>

     
<div class="md:col-span-3">
      <label class="block text-xs text-slate-500 mb-1">Số phiếu</label>
      <input name="so_phieu" value="{{ $so_phieu }}" class="w-full rounded-lg border px-3 py-2" placeholder="A001 ...">
    </div>
    <div class="md:col-span-3">
      <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
      <input name="q" value="{{ $q }}" class="w-full rounded-lg border px-3 py-2" placeholder="Tên HS, trường, số phiếu, dân tộc…">
    </div>

    <div class="md:col-span-2">
      <label class="block text-xs text-slate-500 mb-1">Mỗi trang</label>
      <select name="per_page" class="w-full rounded-lg border px-3 py-2">
        @foreach([25,50,100,200] as $pp)
          <option value="{{ $pp }}" @selected($per_page==$pp)>{{ $pp }}</option>
        @endforeach
      </select>
    </div>

    <div class="md:col-span-12">
  {{-- Quay lại trang tra cứu (ưu tiên lịch sử, fallback về route tra cứu) --}}
  <button type="button"
          class="rounded-lg border px-3 py-2 hover:bg-slate-50 mr-2"
          onclick=" window.location.href='{{ route('front.reports.search.index') }}'; ">
    ← Quay lại tra cứu
  </button>

  <button class="rounded-lg bg-slate-800 px-3 py-2 text-white hover:bg-slate-900">Lọc</button>
  <a href="{{ route('front.reports.search.index') }}" class="ml-2 rounded-lg border px-3 py-2 hover:bg-slate-50">Xoá lọc</a>

  <a href=""
     class="ml-2 rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">
    Xuất Excel
  </a>
</div>
 
  </form>

  @php
    // helper nhỏ để tạo link sắp xếp
    function sort_link($key, $label, $currentBy, $currentDir) {
        $dir = ($currentBy === $key && $currentDir === 'asc') ? 'desc' : 'asc';
        $qs  = request()->query();
        $qs['sort_by']  = $key;
        $qs['sort_dir'] = $dir;
        $url = request()->url().'?'.http_build_query($qs);
        $arrow = $currentBy === $key ? ($currentDir==='asc' ? '▲' : '▼') : '';
        return '<a href="'.$url.'" class="hover:underline">'.$label.' '.$arrow.'</a>';
    }
  @endphp

  <div class="-mx-4 overflow-x-auto">
    <table class="min-w-[1200px] w-full">
      <thead class="bg-slate-100">
        <tr>
          <th class="px-3 py-2 text-left">{!! sort_link('full_name','Họ tên', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('dob','Ngày sinh', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('ethnicity','Dân tộc', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('religion','Tôn giáo', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('so_phieu','Số phiếu', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('head_name','Chủ hộ', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('province','Tỉnh/TP', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('district','Quận/Huyện', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('ward','Phường/Xã', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('village','Thôn/Xóm', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('class_label','Lớp', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('school_name','Trường', $sort_by, $sort_dir) !!}</th>
          <th class="px-3 py-2 text-left">{!! sort_link('sc_name','Hoàn cảnh ĐB', $sort_by, $sort_dir) !!}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($items as $r)
          <tr class="border-b hover:bg-slate-50">
            <td class="px-3 py-2">{{ $r['full_name'] }}</td>
            <td class="px-3 py-2">{{ $r['dob'] }}</td>
            <td class="px-3 py-2">{{ $r['ethnicity'] }}</td>
            <td class="px-3 py-2">{{ $r['religion'] }}</td>
            <td class="px-3 py-2">{{ $r['so_phieu'] }}</td>
            <td class="px-3 py-2">{{ $r['head_name'] }}</td>
            <td class="px-3 py-2">{{ $r['province'] }}</td>
            <td class="px-3 py-2">{{ $r['district'] ?? '—' }}</td>
            <td class="px-3 py-2">{{ $r['ward'] }}</td>
            <td class="px-3 py-2">{{ $r['village'] }}</td>
            <td class="px-3 py-2">{{ $r['class_label'] }}</td>
            <td class="px-3 py-2">{{ $r['school'] }}</td>
            <td class="px-3 py-2">{{ $r['sc_name'] }}</td>
          </tr>
        @empty
          <tr><td class="px-3 py-2" colspan="13">Chưa có dữ liệu.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $rows->withQueryString()->links() }}
  </div>
</div>
@endsection
