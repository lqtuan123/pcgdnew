@extends('layouts.admin')

@section('title','Sửa trường học')
@section('crumb','Trường học')

@section('content')
  @include('admin.schools._form', [
    'action' => route('admin.schools.update', $school),
    'method' => 'PUT',
    'school' => $school,             {{-- sửa --}}
    'types'  => $types ?? [],
    'levels' => $levels ?? [],
  ])
@endsection
