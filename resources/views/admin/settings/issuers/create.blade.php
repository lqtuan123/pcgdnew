@extends('layouts.admin')
@section('title','Thêm đơn vị cấp')
@section('crumb','Cài đặt / Đơn vị cấp văn bản / Thêm mới')

@section('content')
<article class="card">
  <form method="POST" action="{{ route('admin.settings.issuers.store') }}">
    @include('admin.settings.issuers._form')
  </form>
</article>
@endsection
