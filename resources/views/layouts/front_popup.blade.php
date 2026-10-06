<!doctype html>
<html lang="vi" x-data="{ open:false, dark:false }" :class="dark ? 'dark' : ''">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', config('app.name'))</title>
  <x-favicon />
  @vite(['resources/css/app.css','resources/js/app.js'])

  {{-- Nơi view con có thể @push vào <head> (CSS/JS sớm, meta...) --}}
  @stack('head')
</head>
<body class="bg-white text-slate-800 dark:bg-slate-900 dark:text-slate-100">
  {{-- Navbar --}}
  

  <main>
    @if ($errors->any())
      <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
        <div class="font-medium mb-1">Có lỗi khi lưu:</div>
        <ul class="list-disc ml-5">
          @foreach ($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    @if (session('success'))
      <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
        {{ session('success') }}
      </div>
    @endif
    @yield('content')
  </main>

  {{-- Footer --}}
  @include('partials.front.footer')

  {{-- Nơi view con @push JS cuối trang (khuyến nghị đặt script ở đây) --}}
  @stack('scripts')
</body>
</html>
