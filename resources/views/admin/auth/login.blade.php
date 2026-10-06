<!doctype html>
<html lang="vi" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng nhập Admin · {{ config('app.name') }}</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 to-slate-100 text-slate-800 dark:from-slate-950 dark:to-slate-900 dark:text-slate-100">
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-5xl">

      <!-- Header brand (trên mobile cũng thấy) -->
      <div class="mb-6 flex items-center justify-center gap-3">
        <div class="h-15 w-15 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow">
          <x-logo class="h-6 w-6" />
        </div>
        <div class="text-lg font-semibold">Phần mềm Phổ Cập</div>
      </div>

      <!-- Card 2 cột: trái giới thiệu, phải form -->
      <div class="rounded-3xl border bg-white shadow-xl ring-1 ring-black/5 overflow-hidden dark:bg-slate-900 dark:border-slate-800">
        <div class="grid md:grid-cols-5">
          <!-- Cột trái (ẩn trên mobile) -->
          <div class="hidden md:flex md:col-span-2 bg-gradient-to-br from-blue-600 to-indigo-600 text-white p-10">
            <div class="flex flex-col justify-center">
              <div class="flex items-center gap-3">
                
                <div class="text-xl font-semibold">Bảng điều khiển</div>
              </div>
              <p class="mt-4 text-white/85 leading-relaxed">
                Chào mừng quay lại khu vực quản trị. Vui lòng đăng nhập để tiếp tục.
              </p>
            </div>
          </div>

          <!-- Cột phải: form -->
          <div class="md:col-span-3 p-6 sm:p-8">
            <h1 class="text-xl font-semibold">Đăng nhập Quản trị</h1>
            <p class="mt-1 text-sm text-slate-500">{{ config('app.name') }}</p>

            <!-- MOCK: chỉ UI, bấm Đăng nhập sẽ chuyển /admin sau 800ms -->
            <form x-data="{ showPwd:false, loading:false }"
                  @submit.prevent="loading=true; setTimeout(()=>{ window.location.href='{{ url('/admin') }}' }, 800)"
                  class="mt-6 space-y-5">

              <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" name="email" type="email" required placeholder="admin@example.com"
                  class="mt-1 block w-full rounded-xl border-slate-300 bg-white shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:bg-slate-800 dark:border-slate-700" />
              </div>

              <div>
                <label for="password" class="block text-sm font-medium">Mật khẩu</label>

                <div class="relative mt-1" x-data="{ showPwd:false }">
                    <input :type="showPwd ? 'text' : 'password'"
                        id="password" name="password" required placeholder="••••••••"
                        class="block w-full rounded-xl border-slate-300 bg-white pr-10 shadow-sm
                                focus:border-blue-600 focus:ring-blue-600
                                dark:bg-slate-800 dark:border-slate-700" />

                    <!-- Nút mắt, căn giữa theo chiều dọc input -->
                    <button type="button"
                            @click="showPwd = !showPwd"
                            class="absolute inset-y-0 right-0 px-3 flex items-center
                                text-slate-500 hover:bg-slate-100 rounded-lg
                                dark:hover:bg-slate-700"
                            aria-label="Toggle password">
                    <svg x-show="!showPwd" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 4.5C7.305 4.5 3.334 7.364 1.5 12c1.834 4.636 5.805 7.5 10.5 7.5S20.666 16.636 22.5 12C20.666 7.364 16.695 4.5 12 4.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                    </svg>
                    <svg x-show="showPwd" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3 3l18 18-1.5 1.5L17.3 20.3A11.3 11.3 0 0112 21C7.305 21 3.334 18.136 1.5 13.5a12.95 12.95 0 013.77-5.22L1.5 3 3 1.5 22.5 21 21 22.5 17.3 18.8M7.2 8.7A9.84 9.84 0 003.1 13.5C4.934 18.136 8.905 21 13.6 21c1.37 0 2.68-.24 3.9-.68l-2.04-2.04a4.5 4.5 0 01-6.26-6.26L7.2 8.7z"/>
                    </svg>
                    </button>
                </div>
                </div>


              <div class="flex items-center justify-between">
                <label class="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" class="rounded border-slate-300 text-blue-600 focus:ring-blue-600">
                  Ghi nhớ
                </label>
               <a href="{{ route('admin.forgot') }}" class="text-sm text-blue-600 hover:underline">Quên mật khẩu?</a>

              </div>

              <button class="w-full inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 font-medium text-white shadow-sm hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-300"
                      :class="loading && 'opacity-75 cursor-wait'">
                <svg x-show="loading" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3.5-3.5L12 0v4a8 8 0 100 16v4l3.5-3.5L12 20v4a8 8 0 01-8-8z"/>
                </svg>
                Đăng nhập
              </button>
            </form>
          </div>
        </div>
      </div>

      <p class="mt-6 text-center text-xs text-slate-500">
       &copy; 2025 Phần mềm Phổ Cập 
      </p>
    </div>
  </div>
</body>
</html>
