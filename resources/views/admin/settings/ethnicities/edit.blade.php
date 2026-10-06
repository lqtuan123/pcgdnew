@extends('layouts.admin')
@section('title','Sửa dân tộc')
@section('crumb','Cài đặt / Dân tộc / Sửa')


@section('content')
<article class="card">
<form method="POST" action="{{ route('admin.settings.ethnicities.update',$ethnicity) }}">
@method('PUT')
@include('admin.settings.ethnicities._form')
</form>
</article>
@endsection