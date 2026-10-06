@extends('layouts.admin')
@section('title','Thêm hoàn cảnh đặc biệt')
@section('crumb','Cài đặt / Hoàn cảnh đặc biệt / Thêm mới')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Thêm hoàn cảnh đặc biệt</h1>

<form method="POST" action="{{ route('admin.settings.special-circumstances.store') }}">
@include('admin.settings.special-circumstances._form')
</form>
</article>
@endsection
