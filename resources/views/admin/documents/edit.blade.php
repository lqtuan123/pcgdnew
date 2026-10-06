@extends('layouts.admin')
@section('title','Sửa văn bản')
@section('crumb','Quản lý / Văn bản / Sửa')

@section('content')
<article class="card">
  <form method="POST" action="{{ route('admin.documents.update',$document) }}" enctype="multipart/form-data">
    @method('PUT')
    @include('admin.documents._form')
  </form>
</article>
@endsection
