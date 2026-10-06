@extends('layouts.admin')
@section('title','Thêm diện ưu tiên')
@section('crumb','Cài đặt / Diện ưu tiên / Thêm mới')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Thêm diện ưu tiên</h1>

<form method="POST" action="{{ route('admin.settings.priority-categories.store') }}">
@include('admin.settings.priority-categories._form')
</form>
</article>
@endsection
