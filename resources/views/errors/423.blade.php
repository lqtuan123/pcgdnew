@extends('layouts.front')
@section('title', 'Khu vực đang bị khóa')

@section('content')
<div class="min-h-screen bg-gray-50 py-12">
  <div class="mx-auto max-w-3xl px-4">
    <div class="bg-white border shadow-sm rounded-xl p-8">
      <div class="flex items-start gap-4">
        <div class="shrink-0 rounded-full bg-amber-100 text-amber-600 h-12 w-12 grid place-items-center text-xl">423</div>
        <div class="grow">
          <h1 class="text-2xl font-bold text-gray-900 mb-2">Địa bàn đang bị khóa</h1>
          @php
            $msg = isset($exception) && method_exists($exception, 'getMessage') && $exception->getMessage()
                ? $exception->getMessage()
                : 'Chức năng này tạm thời bị khóa tại địa bàn bạn chọn.';
          @endphp
          <p class="text-gray-600">{{ $msg }}</p>

          <div class="mt-6 flex items-center gap-3">
            <a href="{{ url()->previous() }}" class="rounded-lg border px-4 py-2 hover:bg-gray-50">Quay lại</a>
            <a href="{{ route('front.home') }}" class="rounded-lg bg-slate-800 px-4 py-2 text-white hover:bg-slate-900">Về trang chủ</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
