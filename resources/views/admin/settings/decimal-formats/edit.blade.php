@extends('layouts.admin')
@section('title','Sửa cấu hình hiển thị thập phân')
@section('crumb','Cài đặt / Cấu hình hiển thị thập phân / Sửa')

@section('content')
<article class="card">
<form method="POST" action="{{ route('admin.settings.decimal-formats.update',$decimalFormat) }}">
@method('PUT')
@include('admin.settings.decimal-formats._form')
</form>
</article>
@endsection
