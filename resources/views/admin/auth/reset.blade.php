<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đặt lại mật khẩu · {{ config('app.name') }}</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800">
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Brand -->
      <div class="mb-6 flex items-center justify-center gap-3">
        <div class="h-10 w-10 rounded-2xl bg-white text-blue-600 ring-1 ring-blue-200 flex items-center justify-center shadow">
          <x-logo class="h-6 w-6" />
        </div>
        <div class="text-lg font-semibold">{{ config('app.name') }}</div>
      </div>

      <div class="rounded-2xl border bg-white p-6 shadow-sm dark:bg-slate-900 dark:border-slate-800">
        <h1 class="text-lg font-semibold">Đặt lại mật khẩu</h1>
        <p class="mt-1 text-sm text-slate-500">
          Nhập email và mật khẩu mới. Đây là bản <strong>mock</strong> để dựng giao diện.
          @if(request('token'))
            <span class="ml-1">Token: <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">{{ request('token') }}</code></span>
          @endif
        </p>

        <form
          x-data="{
            show1:false, show2:false, loading:false, p:'', pc:'',
            get ok(){ return this.p.length >= 6 && this.p === this.pc }
          }"
          @submit.prevent="
            if(!ok){ alert('Mật khẩu chưa hợp lệ hoặc không khớp.'); return }
            loading = true;
            setTimeout(()=>{ alert('Đặt lại thành công (mock).'); window.location.href='{{ route('admin.login') }}' }, 800)
          "
          class="mt-5 space-y-4">

          <!-- Hidden token (mock) -->
          <input type="hidden" name="token" value="{{ request('token') }}">

          <div>
            <label class="block text-sm font-medium" for="email">Email</label>
            <input id="email" name="email" type="email" required placeholder="admin@example.com"
                   class="mt-1 block w-full rounded-xl border-slate-300 focus:border-blue-600 focus:ring-blue-600" />
          </div>

          <!-- New password -->
          <div>
            <label class="block text-sm font-medium" for="password">Mật khẩu mới</label>
            <div class="relative mt-1">
              <input :type="show1 ? 'text' : 'password'"
                     id="password" name="password" x-model="p" required placeholder="••••••••"
                     class="block w-full rounded-xl border-slate-300 bg-white pr-10 shadow-sm
                            focus:border-blue-600 focus:ring-blue-600" />
              <button type="button" @click="show1=!show1"
                      class="absolute inset-y-0 right-0 px-3 flex items-center rounded-lg text-slate-500 hover:bg-slate-100"
                      aria-label="Toggle password">
                <svg x-show="!show1" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7.305 4.5 3.334 7.364 1.5 12c1.834 4.636 5.805 7.5 10.5 7.5S20.666 16.636 22.5 12C20.666 7.364 16.695 4.5 12 4.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/></svg>
                <svg x-show="show1" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3l18 18-1.5 1.5L17.3 20.3A11.3 11.3 0 0112 21C7.305 21 3.334 18.136 1.5 13.5a12.95 12.95 0 013.77-5.22L1.5 3 3 1.5 22.5 21 21 22.5 17.3 18.8M7.2 8.7A9.84 9.84 0 003.1 13.5C4.934 18.136 8.905 21 13.6 21c1.37 0 2.68-.24 3.9-.68l-2.04-2.04a4.5 4.5 0 01-6.26-6.26L7.2 8.7z"/></svg>
              </button>
            </div>
            <p class="mt-1 text-xs text-slate-500">Tối thiểu 6 ký tự.</p>
          </div>

          <!-- Confirm password -->
          <div>
            <label class="block text-sm font-medium" for="password_confirmation">Xác nhận mật khẩu</label>
            <div class="relative mt-1">
              <input :type="show2 ? 'text' : 'password'"
                     id="password_confirmation" name="password_confirmation" x-model="pc" required placeholder="••••••••"
                     class="block w-full rounded-xl border-slate-300 bg-white pr-10 shadow-sm
                            focus:border-blue-600 focus:ring-blue-600" />
              <button type="button" @click="show2=!show2"
                      class="absolute inset-y-0 right-0 px-3 flex items-center rounded-lg text-slate-500 hover:bg-slate-100"
                      aria-label="Toggle password">
                <svg x-show="!show2" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7.305 4.5 3.334 7.364 1.5 12c1.834 4.636 5.805 7.5 10.5 7.5S20.666 16.636 22.5 12C20.666 7.364 16.695 4.5 12 4.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/></svg>
                <svg x-show="show2" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M3 3l18 18-1.5 1.5L17.3 20.3A11.3 11.3 0 0112 21C7.305 21 3.334 18.136 1.5 13.5a12.95 12.95 0 013.77-5.22L1.5 3 3 1.5 22.5 21 21 22.5 17.3 18.8M7.2 8.7A9.84 9.84 0 003.1 13.5C4.934 18.136 8.905 21 13.6 21c1.37 0 2.68-.24 3.9-.68l-2.04-2.04a4.5 4.5 0 01-6.26-6.26L7.2 8.7z"/></svg>
              </button>
            </div>
            <p class="mt-1 text-xs text-red-600" x-show="p && pc && p !== pc">Mật khẩu không khớp.</p>
          </div>

          <button
            class="w-full rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 font-medium text-white shadow-sm hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-300 disabled:opacity-60"
            :disabled="!ok || loading">
            <span x-show="!loading">Đặt lại mật khẩu</span>
            <span x-show="loading" class="inline-flex items-center gap-2">
              <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3.5-3.5L12 0v4a8 8 0 100 16v4l3.5-3.5L12 20v4a8 8 0 01-8-8z"/>
              </svg>
              Đang xử lý…
            </span>
          </button>

          <a href="{{ route('admin.login') }}" class="block text-center text-sm text-blue-600 hover:underline">← Quay lại đăng nhập</a>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
