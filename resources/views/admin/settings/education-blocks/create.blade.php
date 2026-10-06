@extends('layouts.admin')
@section('title','Thêm khối học')
@section('crumb','Cài đặt / Khối học / Thêm mới')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Thêm khối học</h1>

<form method="POST" action="{{ route('admin.settings.education-blocks.store') }}">
@include('admin.settings.education-blocks._form')
</form>
</article>
@endsection
