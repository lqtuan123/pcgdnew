@extends('layouts.admin')
@section('title','Sửa hoàn cảnh đặc biệt')
@section('crumb','Cài đặt / Hoàn cảnh đặc biệt / Sửa')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Sửa hoàn cảnh đặc biệt</h1>

<form method="POST" action="{{ route('admin.settings.special-circumstances.update', $specialCircumstance) }}">
@method('PUT')
@include('admin.settings.special-circumstances._form')
</form>
</article>
@endsection
