<!doctype html>
<html lang="vi" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng nhập · {{ config('app.name') }}</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-b from-slate-50 to-slate-100 text-slate-800 dark:from-slate-950 dark:to-slate-900 dark:text-slate-100">
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-5xl">

      <!-- Header brand -->
      <div class="mb-6 flex items-center justify-center gap-3">
        @php
          use App\Helpers\LogoHelper;
          $logo = LogoHelper::getActiveLogo();
        @endphp
        
        @if($logo)
          <img src="{{ $logo['url'] }}" 
               alt="{{ $logo['name'] ?? 'Logo' }}" 
               width="{{ $logo['width'] }}" 
               height="{{ $logo['height'] }}"
               class="h-8 w-auto">
        @else
          <div class="h-8 w-8 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white flex items-center justify-center shadow">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
          </div>
        @endif
        
        <div class="text-lg font-semibold">{{ $logo['name'] ?? config('app.name', 'Phần mềm Phổ Cập') }}</div>
      </div>

      <!-- Card 2 cột: trái giới thiệu, phải form -->
      <div class="rounded-3xl border bg-white shadow-xl ring-1 ring-black/5 overflow-hidden dark:bg-slate-900 dark:border-slate-800">
        <div class="grid md:grid-cols-5">
          <!-- Cột trái (ẩn trên mobile) -->
          <div class="hidden md:flex md:col-span-2 bg-gradient-to-br from-green-600 to-emerald-600 text-white p-10">
            <div class="flex flex-col justify-center">
              <div class="flex items-center gap-3">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                <div class="text-xl font-semibold">Đăng nhập</div>
              </div>
              <p class="mt-4 text-white/85 leading-relaxed">
                Chào mừng bạn quay lại hệ thống. Vui lòng đăng nhập để tiếp tục sử dụng các chức năng.
              </p>
            </div>
          </div>

          <!-- Cột phải: form -->
          <div class="md:col-span-3 p-6 sm:p-8">
            <h1 class="text-xl font-semibold">Đăng nhập</h1>
            <p class="mt-1 text-sm text-slate-500">{{ config('app.name') }}</p>

            <!-- Session Status -->
            @if (session('status'))
              <div class="mt-6 rounded-lg bg-green-50 border border-green-200 p-4">
                <div class="flex">
                  <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                  <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('status') }}</p>
                  </div>
                </div>
              </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
              @csrf

              <!-- Email Address -->
              <div>
                <label for="email" class="block text-sm font-medium">Email</label>
                <input id="email" 
                       name="email" 
                       type="email" 
                       value="{{ old('email') }}"
                       required 
                       autofocus
                       autocomplete="username"
                       placeholder="admin@example.com"
                       class="mt-1 block w-full rounded-xl border-slate-300 bg-white shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:bg-slate-800 dark:border-slate-700 @error('email') border-red-300 focus:border-red-600 focus:ring-red-600 @enderror" />
                @error('email')
                  <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
              </div>

              <!-- Password -->
              <div>
                <label for="password" class="block text-sm font-medium">Mật khẩu</label>
                <div class="relative mt-1" x-data="{ showPwd: false }">
                  <input :type="showPwd ? 'text' : 'password'"
                         id="password" 
                         name="password" 
                         required 
                         autocomplete="current-password"
                         placeholder="••••••••"
                         class="block w-full rounded-xl border-slate-300 bg-white pr-10 shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:bg-slate-800 dark:border-slate-700 @error('password') border-red-300 focus:border-red-600 focus:ring-red-600 @enderror" />

                  <!-- Nút mắt, căn giữa theo chiều dọc input -->
                  <button type="button"
                          @click="showPwd = !showPwd"
                          class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-500 hover:bg-slate-100 rounded-lg dark:hover:bg-slate-700"
                          aria-label="Toggle password">
                    <svg x-show="!showPwd" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 4.5C7.305 4.5 3.334 7.364 1.5 12c1.834 4.636 5.805 7.5 10.5 7.5S20.666 16.636 22.5 12C20.666 7.364 16.695 4.5 12 4.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                    </svg>
                    <svg x-show="showPwd" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M3 3l18 18-1.5 1.5L17.3 20.3A11.3 11.3 0 0112 21C7.305 21 3.334 18.136 1.5 13.5a12.95 12.95 0 013.77-5.22L1.5 3 3 1.5 22.5 21 21 22.5 17.3 18.8M7.2 8.7A9.84 9.84 0 003.1 13.5C4.934 18.136 8.905 21 13.6 21c1.37 0 2.68-.24 3.9-.68l-2.04-2.04a4.5 4.5 0 01-6.26-6.26L7.2 8.7z"/>
                    </svg>
                  </button>
                </div>
                @error('password')
                  <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
              </div>

              <!-- Remember Me & Forgot Password -->
              <div class="flex items-center justify-between">
                <label class="inline-flex items-center gap-2 text-sm">
                  <input type="checkbox" 
                         id="remember"
                         name="remember" 
                         class="rounded border-slate-300 text-blue-600 focus:ring-blue-600">
                  Ghi nhớ
                </label>
                @if (Route::has('password.request'))
                  <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">Quên mật khẩu?</a>
                @endif
              </div>

              <!-- Submit Button -->
              <button type="submit" 
                      class="w-full inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2.5 font-medium text-white shadow-sm hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-colors">
                Đăng nhập
              </button>
            </form>

            <!-- Phân cách -->
            <div class="relative mt-6">
              <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-slate-300" />
              </div>
              <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white text-slate-500"></span>
              </div>
            </div>

            <!-- Link đăng nhập Google -->
            {{-- <div class="mt-6 text-center">
              <p class="text-sm text-slate-600 mb-3">
                Bạn muốn đăng nhập bằng Google?
              </p>
              <a href="{{ route('auth.google.login') }}" 
                 class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                <svg class="mr-2 h-5 w-5" viewBox="0 0 24 24">
                  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Đăng nhập bằng Google
              </a>
            </div> --}}
          </div>
        </div>
      </div>

      <p class="mt-6 text-center text-xs text-slate-500">
       &copy; 2025 Phần mềm Phổ Cập 
      </p>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const rememberCheckbox = document.getElementById('remember');
        const loginForm = document.querySelector('form');

        // Khôi phục thông tin đăng nhập đã lưu
        const savedEmail = localStorage.getItem('phocap_remembered_email');
        const savedPassword = localStorage.getItem('phocap_remembered_password');
        const wasRemembered = localStorage.getItem('phocap_remember_login') === 'true';

        if (wasRemembered && savedEmail && savedPassword) {
            if (emailInput) emailInput.value = savedEmail;
            if (passwordInput) passwordInput.value = savedPassword;
            if (rememberCheckbox) rememberCheckbox.checked = true;
        }

        // Lưu thông tin đăng nhập khi submit form
        if (loginForm && rememberCheckbox) {
            loginForm.addEventListener('submit', function(e) {
                if (rememberCheckbox.checked) {
                    // Lưu thông tin đăng nhập
                    const email = emailInput ? emailInput.value : '';
                    const password = passwordInput ? passwordInput.value : '';
                    
                    localStorage.setItem('phocap_remembered_email', email);
                    localStorage.setItem('phocap_remembered_password', password);
                    localStorage.setItem('phocap_remember_login', 'true');
                } else {
                    // Xóa thông tin đăng nhập đã lưu
                    localStorage.removeItem('phocap_remembered_email');
                    localStorage.removeItem('phocap_remembered_password');
                    localStorage.removeItem('phocap_remember_login');
                }
            });
        }

        // Xóa thông tin đăng nhập khi bỏ tích "Ghi nhớ"
        if (rememberCheckbox) {
            rememberCheckbox.addEventListener('change', function() {
                if (!this.checked) {
                    localStorage.removeItem('phocap_remembered_email');
                    localStorage.removeItem('phocap_remembered_password');
                    localStorage.removeItem('phocap_remember_login');
                }
            });
        }
    });
  </script>
</body>
</html>
