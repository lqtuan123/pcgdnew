@extends('layouts.admin')
@section('title','Thêm diện cư trú')
@section('crumb','Cài đặt / Diện cư trú / Thêm mới')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Thêm diện cư trú</h1>

<form method="POST" action="{{ route('admin.settings.residence-types.store') }}">
@include('admin.settings.residence-types._form')
</form>
</article>
@endsection
