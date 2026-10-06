@extends('layouts.admin')
@section('title','Sửa tình trạng cư trú')
@section('crumb','Cài đặt / Tình trạng cư trú / Sửa')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Sửa tình trạng cư trú</h1>

<form method="POST" action="{{ route('admin.settings.residence-statuses.update', $residenceStatus) }}">
@method('PUT')
@include('admin.settings.residence-statuses._form')
</form>
</article>
@endsection
