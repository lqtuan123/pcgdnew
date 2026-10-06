@extends('layouts.admin')
@section('title','Thêm báo cáo chi tiết')
@section('crumb','Quản lý / Báo cáo / Thêm báo cáo')

@section('content')
<div class="max-w-4xl mx-auto">
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold">Thêm báo cáo chi tiết</h1>
      <a href="{{ route('admin.reports.show', request('group_id') ? \App\Models\ReportGroup::find(request('group_id')) : $reportGroups->first()) }}" class="btn-soft">← Quay lại</a>
    </div>

    <form action="{{ route('admin.report-details.store') }}" method="POST">
      @csrf
      @include('admin.report-details._form')
      
      <div class="mt-6 flex items-center gap-3">
        <button type="submit" class="btn-primary">Tạo báo cáo</button>
        <a href="{{ route('admin.reports.show', request('group_id') ? \App\Models\ReportGroup::find(request('group_id')) : $reportGroups->first()) }}" class="btn-soft">Huỷ</a>
      </div>
    </form>
  </div>
</div>
@endsection
