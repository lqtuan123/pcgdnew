@extends('layouts.admin')
@section('title','Sửa tôn giáo')
@section('crumb','Cài đặt / Tôn giáo / Sửa')

@section('content')
<article class="card">
  <form method="POST" action="{{ route('admin.settings.religions.update',$religion) }}">
    @method('PUT')
    @include('admin.settings.religions._form')
  </form>
</article>
@endsection
