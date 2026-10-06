@extends('layouts.front')
@section('title', 'Không có quyền truy cập')

@section('content')
<div class="min-h-screen bg-gray-50 py-12">
  <div class="mx-auto max-w-3xl px-4">
    <div class="bg-white border shadow-sm rounded-xl p-8">
      <div class="flex items-start gap-4">
        <div class="shrink-0 rounded-full bg-red-100 text-red-600 h-12 w-12 grid place-items-center text-xl">403</div>
        <div class="grow">
          <h1 class="text-2xl font-bold text-gray-900 mb-2">Bạn không có quyền xem nội dung này</h1>
          @php
            $msg = isset($exception) && method_exists($exception, 'getMessage') && $exception->getMessage()
                ? $exception->getMessage()
                : 'Vui lòng liên hệ quản trị để được cấp quyền.';
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
