@extends('layouts.admin')
@section('title','Chỉnh sửa nhóm báo cáo')
@section('crumb','Quản lý / Báo cáo / Chỉnh sửa')

@section('content')
<div class="max-w-4xl mx-auto">
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold">Chỉnh sửa nhóm báo cáo: {{ $reportGroup->name }}</h1>
      <a href="{{ route('admin.reports.index') }}" class="btn-soft">← Quay lại</a>
    </div>

    <form action="{{ route('admin.reports.update', $reportGroup) }}" method="POST">
      @csrf
      @method('PUT')
      @include('admin.reports._form')
      
      <div class="mt-6 flex items-center gap-3">
        <button type="submit" class="btn-primary">Cập nhật</button>
        <a href="{{ route('admin.reports.index') }}" class="btn-soft">Huỷ</a>
      </div>
    </form>
  </div>
</div>
@endsection
