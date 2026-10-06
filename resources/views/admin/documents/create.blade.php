@extends('layouts.admin')
@section('title','Thêm văn bản')
@section('crumb','Quản lý / Văn bản / Thêm mới')

@section('content')
<article class="card">
  <form method="POST" action="{{ route('admin.documents.store') }}" enctype="multipart/form-data">
    @include('admin.documents._form')
  </form>
</article>
@endsection
