@extends('layouts.admin')
@section('title','Thêm nhóm báo cáo')
@section('crumb','Quản lý / Báo cáo / Thêm mới')

@section('content')
<div class="max-w-4xl mx-auto">
  <div class="card">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-semibold">Thêm nhóm báo cáo mới</h1>
      <a href="{{ route('admin.reports.index') }}" class="btn-soft">← Quay lại</a>
    </div>

    <form action="{{ route('admin.reports.store') }}" method="POST">
      @csrf
      @include('admin.reports._form')
      
      <div class="mt-6 flex items-center gap-3">
        <button type="submit" class="btn-primary">Tạo nhóm báo cáo</button>
        <a href="{{ route('admin.reports.index') }}" class="btn-soft">Huỷ</a>
      </div>
    </form>
  </div>
</div>
@endsection
