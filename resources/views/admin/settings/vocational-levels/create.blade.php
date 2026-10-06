@extends('layouts.admin')
@section('title','Thêm bậc tốt nghiệp nghề')
@section('crumb','Cài đặt / Bậc tốt nghiệp nghề / Thêm mới')

@section('content')
<article class="card">
<h1 class="text-lg font-semibold mb-4">Thêm bậc tốt nghiệp nghề</h1>

<form method="POST" action="{{ route('admin.settings.vocational-levels.store') }}">
@include('admin.settings.vocational-levels._form')
</form>
</article>
@endsection
