@extends('layouts.admin')
@section('title','Sửa bậc tốt nghiệp')
@section('crumb','Cài đặt / Bậc tốt nghiệp / Sửa')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Sửa bậc tốt nghiệp</h1>

<form method="POST" action="{{ route('admin.settings.graduation-levels.update', $graduationLevel) }}">
@method('PUT')
@include('admin.settings.graduation-levels._form')
</form>
</article>
@endsection
