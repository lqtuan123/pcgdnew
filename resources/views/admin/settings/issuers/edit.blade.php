@extends('layouts.admin')
@section('title','Sửa đơn vị cấp')
@section('crumb','Cài đặt / Đơn vị cấp văn bản / Sửa')

@section('content')
<article class="card">
  <form method="POST" action="{{ route('admin.settings.issuers.update',$issuer) }}">
    @method('PUT')
    @include('admin.settings.issuers._form')
  </form>
</article>
@endsection
