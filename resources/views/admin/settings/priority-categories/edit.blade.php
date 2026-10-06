@extends('layouts.admin')
@section('title','Sửa diện ưu tiên')
@section('crumb','Cài đặt / Diện ưu tiên / Sửa')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Sửa diện ưu tiên</h1>

<form method="POST" action="{{ route('admin.settings.priority-categories.update', $priorityCategory) }}">
@method('PUT')
@include('admin.settings.priority-categories._form')
</form>
</article>
@endsection
