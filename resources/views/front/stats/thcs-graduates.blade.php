@extends('layouts.front')
@section('title', 'Đối tượng 15-18 tuổi tốt nghiệp THCS')
@section('content')
<div class="container mx-auto px-4 py-6">
  <div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">6.2 Danh sách đối tượng 15-18 tuổi tốt nghiệp THCS</h1>
    <p class="text-slate-600 text-sm">Xuất danh sách đối tượng từ 15-18 tuổi đã tốt nghiệp THCS</p>
  </div>

  <div class="bg-white border rounded-xl p-6">
    <form method="GET" action="{{ route('front.stats.thcs-graduates.export') }}" class="space-y-4">
      @if($wards->count())
        <div class="max-w-md">
          <label class="block text-sm font-medium text-slate-700 mb-2">Chọn xã</label>
          <select name="ward_id" class="w-full px-3 py-2 border rounded-lg">
            <option value="">-- Tất cả xã --</option>
            @foreach($wards as $w)
              <option value="{{ $w->id }}">{{ $w->name }}</option>
            @endforeach
          </select>
        </div>
      @endif
      
      <div class="flex justify-end">
        <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">
          Xuất Excel
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
