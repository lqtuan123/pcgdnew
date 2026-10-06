@extends('layouts.admin')

@section('title','Thêm trường học')
@section('crumb','Trường học')

@section('content')
  @include('admin.schools._form', [
    'action' => route('admin.schools.store'),
    'method' => 'POST',
    'school' => null,                {{-- tạo mới --}}
    'types'  => $types ?? [],
    'levels' => $levels ?? [],
  ])
@endsection
