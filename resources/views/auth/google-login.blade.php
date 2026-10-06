<!doctype html>
<html lang="vi" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Đăng nhập Google · {{ config('app.name') }}</title>
  <x-favicon />
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
                  <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                  <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                  <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                  <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                <div class="text-xl font-semibold">Đăng nhập Google</div>
              </div>
              <p class="mt-4 text-white/85 leading-relaxed">
                Đăng nhập bằng tài khoản Google của bạn để truy cập hệ thống. Chỉ những tài khoản được cấp quyền mới có thể đăng nhập.
              </p>
            </div>
          </div>

          <!-- Cột phải: form -->
          <div class="md:col-span-3 p-6 sm:p-8">
            <h1 class="text-xl font-semibold">Đăng nhập hệ thống</h1>
            <p class="mt-1 text-sm text-slate-500">{{ config('app.name') }}</p>

            @if($errors->any())
              <div class="mt-6 rounded-lg bg-red-50 border border-red-200 p-4">
                <div class="flex">
                  <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                  </svg>
                  <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">Có lỗi xảy ra:</p>
                    <ul class="mt-1 text-sm text-red-700 list-disc list-inside">
                      @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                      @endforeach
                    </ul>
                  </div>
                </div>
              </div>
            @endif

            <div class="mt-6 space-y-5">
              <!-- Form đăng nhập thông thường -->
              <form method="POST" action="{{ route('auth.login.submit') }}" class="space-y-4">
                @csrf
                <div>
                  <label for="email" class="block text-sm font-medium text-slate-700 mb-1">Tên đăng nhập</label>
                  <input type="email" 
                         id="email" 
                         name="email" 
                         value="{{ old('email') }}"
                         required 
                         class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                         placeholder="Nhập tài khoản">
                  @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                  @enderror
                </div>
                
                <div>
                  <label for="password" class="block text-sm font-medium text-slate-700 mb-1">Mật khẩu</label>
                  <input type="password" 
                         id="password" 
                         name="password" 
                         required 
                         class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                         placeholder="Nhập mật khẩu">
                  @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                  @enderror
                </div>
                
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <input type="checkbox" 
                           id="remember" 
                           name="remember" 
                           class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <label for="remember" class="ml-2 text-sm text-slate-600">Ghi nhớ đăng nhập</label>
                  </div>
                  @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:underline">Quên mật khẩu?</a>
                  @endif
                </div>
                
                <button type="submit" 
                        class="w-full rounded-xl bg-blue-600 px-4 py-3 font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                  Đăng nhập
                </button>
              </form>

              <!-- Phân cách -->
              <div class="relative">
                <div class="absolute inset-0 flex items-center">
                  <div class="w-full border-t border-slate-300" />
                </div>
                <div class="relative flex justify-center text-sm">
                  <span class="px-2 bg-white text-slate-500">Hoặc</span>
                </div>
              </div>

              <!-- Nút đăng nhập Google -->
              <a href="{{ route('auth.google') }}" 
                 class="w-full inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-3 font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors relative z-10 mb-4">
                <svg class="mr-3 h-5 w-5" viewBox="0 0 24 24">
                  <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                  <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                  <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                  <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Đăng nhập bằng Google
              </a>

              <!-- Thông tin -->
              <div class="rounded-lg bg-blue-50 border border-blue-200 p-4 relative z-0">
                <div class="flex">
                  <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                  </svg>
                  <div class="ml-3">
                    <p class="text-sm text-blue-800">
                      <strong>Lưu ý:</strong> Chỉ những tài khoản được quản trị viên cấp quyền mới có thể đăng nhập vào hệ thống.
                    </p>
                    <p class="text-sm text-blue-700 mt-1">
                      Nếu bạn chưa có quyền truy cập, vui lòng liên hệ quản trị viên.
                    </p>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <p class="mt-6 text-center text-xs text-slate-500">
       &copy; 2025 Phần mềm Phổ Cập 
      </p>
    </div>
  </div>

  <!-- SCRIPT MỚI HOÀN CHỈNH -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {

        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const rememberCheckbox = document.getElementById('remember');
        const loginForm = document.querySelector('form[method="POST"]');

        // Hàm lấy cookie
        function getCookie(name) {
            let v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
            return v ? decodeURIComponent(v[2]) : null;
        }

        // Khôi phục dữ liệu đã lưu
        const savedEmail = getCookie("phocap_email");
        const savedPassword = getCookie("phocap_password");

        if (savedEmail) emailInput.value = savedEmail;
        if (savedPassword) passwordInput.value = savedPassword;
        if (savedEmail || savedPassword) rememberCheckbox.checked = true;

        // Lưu khi submit form
        if (loginForm && rememberCheckbox) {
            loginForm.addEventListener('submit', function () {
                if (rememberCheckbox.checked) {
                    document.cookie = "phocap_email=" + encodeURIComponent(emailInput.value) + "; path=/; max-age=2592000";
                    document.cookie = "phocap_password=" + encodeURIComponent(passwordInput.value) + "; path=/; max-age=2592000";
                } else {
                    document.cookie = "phocap_email=; path=/; max-age=0";
                    document.cookie = "phocap_password=; path=/; max-age=0";
                }
            });
        }

        // Xoá cookie khi bỏ tick
        rememberCheckbox.addEventListener('change', function () {
            if (!this.checked) {
                document.cookie = "phocap_email=; path=/; max-age=0";
                document.cookie = "phocap_password=; path=/; max-age=0";
            }
        });
    });
  </script>

</body>
</html>
