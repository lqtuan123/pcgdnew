@extends('layouts.admin')
@section('title','Sửa bậc tốt nghiệp nghề')
@section('crumb','Cài đặt / Bậc tốt nghiệp nghề / Sửa')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Sửa bậc tốt nghiệp nghề</h1>

<form method="POST" action="{{ route('admin.settings.vocational-levels.update', $vocationalLevel) }}">
@method('PUT')
@include('admin.settings.vocational-levels._form')
</form>
</article>
@endsection
