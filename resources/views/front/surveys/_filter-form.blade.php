@php
  $base = $defaultYear;
  [$y1,$y2] = explode('-', $base);
  $years = [($y1-2).'-'.($y1-1), ($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1)];
@endphp

<form method="get" class="mb-3 grid gap-3 md:grid-cols-12">
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
    <input name="so_phieu" value="{{ $soPhieu }}" class="w-full rounded-lg border px-3 py-2" placeholder="VD: AC001">
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Tìm người (tên/CMND/điện thoại)</label>
    <input name="q" value="{{ $q }}" class="w-full rounded-lg border px-3 py-2">
  </div>

  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Độ tuổi (từ)</label>
    <input type="number" min="0" name="min_age" value="{{ old('min_age', $minAge) }}"
           class="w-full rounded-lg border px-3 py-2" placeholder="VD: 5">
  </div>
  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Độ tuổi (đến)</label>
    <input type="number" min="0" name="max_age" value="{{ old('max_age', $maxAge) }}"
           class="w-full rounded-lg border px-3 py-2" placeholder="VD: 18">
  </div>
  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Năm sinh</label>
    <input type="number" min="1900" max="2099" name="birth_year" value="{{ old('birth_year', $birthYear ?? '') }}"
           class="w-full rounded-lg border px-3 py-2" placeholder="VD: 2003">
  </div>
  <div class="md:col-span-3">
    <label class="block text-xs text-slate-500 mb-1">Lớp</label>
    <input name="class_label" value="{{ $classLabel }}" class="w-full rounded-lg border px-3 py-2" placeholder="VD: 1A, 5B">
  </div>
  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Trường</label>
    <input name="school" value="{{ $school }}" class="w-full rounded-lg border px-3 py-2" placeholder="Tên hoặc mã trường">
  </div>

  <div class="md:col-span-6">
    <label class="block text-xs text-slate-500 mb-1">Dân tộc</label>
    <select name="minority" class="w-full rounded-lg border px-3 py-2">
      <option value="" @selected($minority===null || $minority==='')>— Tất cả —</option>
      <option value="0" @selected($minority==='0')>Kinh</option>
      <option value="1" @selected($minority==='1')>Thiểu số</option>
    </select>
  </div>

  

  {{-- Geo filter: chiếm trọn hàng --}}
<div class="md:col-span-12">
  @include('partials.front.geo-filter', [
    'scheme'      => 'new',
    'schemeMode'  => 'new',   // hoặc 'old' / 'new'
    'showHamlet'  => true,
      'schoolWardParam' => 'ward_id',
  'schoolUseCode'   => false,
    // (tùy chọn) trường:
    'showSchool'  => false,
    'enableSchool'=> false,
    'schoolName'  => 'school_id',
    'schoolId'    => request('school_id'),
    'schoolDataUrl' => route('front.schools.datapicker'),
    'wrapperClass'=> 'md:col-span-8 grid md:grid-cols-12 gap-3'
  ])
</div>

  <div class="md:col-span-12 flex flex-wrap items-end gap-2">
    <button class="btn-primary">Lọc</button>
    <a href="{{ route('front.surveys.index') }}" class="btn-soft">Xoá lọc</a>
    {{-- <a href="{{ route('front.surveys.import') }}" class="btn-soft">Nhập từ excel</a>
    <a href="{{ route('front.surveys.create') }}" class="btn-primary">Thêm phiếu mới</a> --}}
  </div>
</form>
