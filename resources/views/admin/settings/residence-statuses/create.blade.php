@extends('layouts.admin')
@section('title','Thêm tình trạng cư trú')
@section('crumb','Cài đặt / Tình trạng cư trú / Thêm mới')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Thêm tình trạng cư trú</h1>

<form method="POST" action="{{ route('admin.settings.residence-statuses.store') }}">
@include('admin.settings.residence-statuses._form')
</form>
</article>
@endsection
