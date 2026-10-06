<!doctype html>
<html lang="vi" class="h-full" x-data="{ sidebarOpen:false, sidebarCollapsed:false, dark:false }" :class="dark ? 'dark' : ''">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin · ' . config('app.name'))</title>
    <x-favicon />
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden bg-gray-50 text-slate-800 dark:bg-slate-900 dark:text-slate-100">
  <div class="h-full w-full flex">
    <!-- Desktop sidebar -->
    <aside class="hidden md:flex sidebar transition-all duration-300" :class="sidebarCollapsed ? 'w-20' : 'w-64'">
      @include('partials.admin.sidebar')
    </aside>

    <!-- Mobile off-canvas -->
    <div x-cloak x-show="sidebarOpen" class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true">
      <div class="absolute inset-0 bg-black/50" @click="sidebarOpen=false"></div>
      <aside class="absolute inset-y-0 left-0 w-72 sidebar p-2">
        @include('partials.admin.sidebar')
      </aside>
    </div>

    <!-- Main -->
    <div class="flex-1 min-w-0 flex flex-col">
      @include('partials.admin.topbar')

      <main class="flex-1 overflow-y-auto">
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

        <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
          @yield('content')
        </div>
      </main>
    </div>
  </div>
    @stack('scripts')
</body>
</html>
