@extends('layouts.admin')
@section('title','Thêm tôn giáo')
@section('crumb','Cài đặt / Tôn giáo / Thêm mới')

@section('content')
<article class="card">
  <form method="POST" action="{{ route('admin.settings.religions.store') }}">
    @include('admin.settings.religions._form')
  </form>
</article>
@endsection
