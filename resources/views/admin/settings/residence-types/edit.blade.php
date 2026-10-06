@extends('layouts.admin')
@section('title','Sửa diện cư trú')
@section('crumb','Cài đặt / Diện cư trú / Sửa')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Sửa diện cư trú</h1>

<form method="POST" action="{{ route('admin.settings.residence-types.update', $residenceType) }}">
@method('PUT')
@include('admin.settings.residence-types._form')
</form>
</article>
@endsection
