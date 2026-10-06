@extends('layouts.admin')
@section('title','Thêm bậc tốt nghiệp')
@section('crumb','Cài đặt / Bậc tốt nghiệp / Thêm mới')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Thêm bậc tốt nghiệp</h1>

<form method="POST" action="{{ route('admin.settings.graduation-levels.store') }}">
@include('admin.settings.graduation-levels._form')
</form>
</article>
@endsection
