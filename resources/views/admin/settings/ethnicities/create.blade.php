@extends('layouts.admin')
@section('title','Thêm dân tộc')
@section('crumb','Cài đặt / Dân tộc / Thêm mới')


@section('content')
<article class="card">
<form method="POST" action="{{ route('admin.settings.ethnicities.store') }}">
@include('admin.settings.ethnicities._form')
</form>
</article>
@endsection