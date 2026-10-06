@extends('layouts.admin')
@section('title','Thêm cấu hình hiển thị thập phân')
@section('crumb','Cài đặt / Cấu hình hiển thị thập phân / Thêm mới')

@section('content')
<article class="card">
<form method="POST" action="{{ route('admin.settings.decimal-formats.store') }}">
@include('admin.settings.decimal-formats._form')
</form>
</article>
@endsection
