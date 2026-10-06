@extends('layouts.admin')
@section('title','Sửa khối học')
@section('crumb','Cài đặt / Khối học / Sửa')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Sửa khối học</h1>

<form method="POST" action="{{ route('admin.settings.education-blocks.update', $educationBlock) }}">
@method('PUT')
@include('admin.settings.education-blocks._form')
</form>
</article>
@endsection
