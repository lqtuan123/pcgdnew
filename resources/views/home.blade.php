@extends('layouts.app')

@section('title','Trang chủ')

@section('content')
  <section class="prose dark:prose-invert max-w-none">
    <h1>Xin chào, Blade + Tailwind</h1>
    <p>Đây là trang mẫu. Bạn có thể bắt đầu dựng giao diện ngay với các lớp Tailwind.</p>
  </section>

  <section class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
    @foreach(range(1,6) as $i)
      <article class="card">
        <h3 class="text-lg font-semibold">Thẻ nội dung #{{ $i }}</h3>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Mô tả ngắn gọn cho khối nội dung.</p>
        <x-button class="mt-4">Xem chi tiết</x-button>
      </article>
    @endforeach
  </section>

  <section class="mt-10 card">
    <h2 class="text-lg font-semibold">Form mẫu</h2>
    <form class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2" method="POST" action="#">
      @csrf
      <x-input name="full_name" label="Họ và tên" placeholder="Nguyễn Văn A" />
      <x-input name="email" type="email" label="Email" placeholder="you@example.com" />
      <div class="sm:col-span-2">
        <x-button type="submit">Gửi</x-button>
      </div>
    </form>
  </section>
@endsection
