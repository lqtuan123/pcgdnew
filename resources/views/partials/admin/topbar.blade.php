 <header class="sticky top-0 z-40 border-b bg-white/80 backdrop-blur dark:bg-slate-900/70">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <button class="md:hidden rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="sidebarOpen = true" aria-label="Open sidebar">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
      </button>

      <div class="hidden md:flex">
        <nav class="text-sm text-slate-500">
          <ol class="flex items-center gap-1">
            <li><a href="{{ route('admin.dashboard') }}" class="hover:underline">Admin</a></li>
            <li class="text-slate-400">/</li>
            <li class="text-slate-700 dark:text-slate-300">@yield('crumb','Bảng điều khiển')</li>
          </ol>
        </nav>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="dark=!dark" title="Dark mode">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3a1 1 0 00-1 1v1a1 1 0 002 0V4a1 1 0 00-1-1zm5.657 2.343a1 1 0 00-1.414 0l-.707.707a1 1 0 001.414 1.414l.707-.707a1 1 0 000-1.414zM21 11h-1a1 1 0 000 2h1a1 1 0 000-2zM6.464 6.464a1 1 0 00-1.414 1.414l.707.707A1 1 0 007.171 7.88l-.707-.707zM4 11H3a1 1 0 000 2h1a1 1 0 000-2zm2.05 7.536a1 1 0 101.414-1.414l-.707-.707a1 1 0 10-1.414 1.414l.707.707zM17.536 17.95a1 1 0 101.414-1.414l-.707-.707a1 1 0 10-1.414 1.414l.707.707zM12 18a1 1 0 00-1 1v1a1 1 0 002 0v-1a1 1 0 00-1-1z"/></svg>
      </button>

      <div x-data="{ open:false }" class="relative">
        <button @click="open=!open" @click.outside="open=false" class="flex items-center gap-2 rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
          <div class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
            <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 12a4 4 0 100-8 4 4 0 000 8zm7 8H5a5 5 0 019-3h-4a5 5 0 00-5 5"/>
            </svg>
          </div>
          <div class="hidden sm:block text-left">
            <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ auth()->user()->name }}</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->getRoleDisplayName() }}</div>
          </div>
          <svg class="h-4 w-4 text-slate-500 dark:text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div x-cloak x-show="open" x-transition class="absolute right-0 mt-2 w-64 rounded-xl border bg-white p-2 text-sm shadow-lg dark:bg-slate-800 dark:border-slate-700">
          {{-- User Info Header --}}
          <div class="px-3 py-2 border-b border-slate-200 dark:border-slate-700 mb-2">
            <div class="flex items-center gap-3">
              <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 12a4 4 0 100-8 4 4 0 000 8zm7 8H5a5 5 0 019-3h-4a5 5 0 00-5 5"/>
                </svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-medium text-slate-900 dark:text-slate-100 truncate">{{ auth()->user()->name }}</div>
                <div class="text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</div>
                <div class="text-xs text-blue-600 dark:text-blue-400">{{ auth()->user()->getRoleDisplayName() }}</div>
              </div>
            </div>
          </div>
          
          {{-- Menu Items --}}
          <button onclick="openProfileModal()" class="w-full text-left flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
            <svg class="h-4 w-4 text-slate-500 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            Hồ sơ cá nhân
          </button>
          
          <button onclick="openChangePasswordModal()" class="w-full text-left flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
            <svg class="h-4 w-4 text-slate-500 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            Đổi mật khẩu
          </button>
          
          {{-- Divider --}}
          <div class="border-t border-slate-200 dark:border-slate-700 my-2"></div>
          
          {{-- Logout Button --}}
          <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <button type="submit" class="w-full text-left flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
              </svg>
              Đăng xuất
            </button>
          </form>
        </div>
      </div>
        {{-- Exit Admin Button --}}
        <a href="{{ route('front.home') }}" class="btn-primary ml-2">Thoát quản trị</a>
    </div>
  </div>
</header>

{{-- Profile Modal --}}
<div id="profileModal" class="fixed inset-0 z-[60] hidden">
  <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity z-[60]"></div>
  
  <div class="fixed inset-0 z-[70] overflow-y-auto">
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
      <div class="relative transform overflow-hidden rounded-2xl border bg-white shadow-xl ring-1 ring-black/5 transition-all sm:my-8 sm:w-full sm:max-w-2xl dark:bg-slate-900 dark:border-slate-800">
        <div class="bg-white px-6 pt-6 pb-4 sm:p-8 dark:bg-slate-900">
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30 sm:mx-0 sm:h-10 sm:w-10">
              <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
              <h3 class="text-xl font-semibold leading-6 text-slate-900 dark:text-slate-100">Hồ sơ cá nhân</h3>
              <div class="mt-2">
                <p class="text-sm text-slate-600 dark:text-slate-400">Xem và cập nhật thông tin cá nhân của bạn</p>
              </div>
            </div>
          </div>

          <form id="profileForm" method="POST" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('PATCH')

            {{-- Thông tin cơ bản --}}
            <div class="space-y-4">
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label for="profile_name" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Họ và tên <span class="text-red-500">*</span>
                  </label>
                  <input type="text" id="profile_name" name="name" value="{{ auth()->user()->name }}" required
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 @error('name') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
                    placeholder="Nhập họ và tên">
                  @error('name')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                      <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                      </svg>
                      {{ $message }}
                    </p>
                  @enderror
                </div>

                <div>
                  <label for="profile_phone" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Số điện thoại
                  </label>
                  <input type="tel" id="profile_phone" name="phone_number" value="{{ auth()->user()->phone_number }}"
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 @error('phone_number') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
                    placeholder="0123 456 789">
                  @error('phone_number')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                      <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                      </svg>
                      {{ $message }}
                    </p>
                  @enderror
                </div>
              </div>

              <div>
                <label for="profile_email" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                  Email
                </label>
                <input type="email" id="profile_email" value="{{ auth()->user()->email }}" disabled
                  class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-600 text-slate-500 dark:text-slate-400 cursor-not-allowed">
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400 flex items-center">
                  <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                  </svg>
                  Email được liên kết với tài khoản Google và không thể thay đổi
                </p>
              </div>
            </div>

            {{-- Thông tin quyền --}}
            <div class="border-t border-slate-200 pt-6 dark:border-slate-700">
              <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-4">Thông tin quyền</h4>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                      </svg>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Vai trò</p>
                      <p class="text-sm text-slate-500 dark:text-slate-400">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ auth()->user()->role === 'admin' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200' }}">
                          {{ auth()->user()->getRoleDisplayName() }}
                        </span>
                      </p>
                    </div>
                  </div>
                </div>

                <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
                  <div class="flex items-center">
                    <div class="flex-shrink-0">
                      <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                      </svg>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Ngày tham gia</p>
                      <p class="text-sm text-slate-500 dark:text-slate-400">{{ auth()->user()->created_at->format('d/m/Y') }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>

        <div class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse sm:space-x-2 sm:space-x-reverse sm:px-8 dark:bg-slate-800">
          <button type="submit" form="profileForm" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800 transition-all duration-200 font-medium shadow-lg hover:shadow-xl">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Cập nhật thông tin
          </button>
          <button type="button" onclick="closeProfileModal()" class="mt-3 inline-flex items-center justify-center px-6 py-3 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 transition-all duration-200 font-medium sm:mt-0">
            Đóng
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Change Password Modal --}}
<div id="changePasswordModal" class="fixed inset-0 z-[60] hidden">
  <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity z-[60]"></div>
  
  <div class="fixed inset-0 z-[70] overflow-y-auto">
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
      <div class="relative transform overflow-hidden rounded-2xl border bg-white shadow-xl ring-1 ring-black/5 transition-all sm:my-8 sm:w-full sm:max-w-lg dark:bg-slate-900 dark:border-slate-800">
        <div class="bg-white px-6 pt-6 pb-4 sm:p-8 dark:bg-slate-900">
          <div class="sm:flex sm:items-start">
            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-green-100 dark:bg-green-900/30 sm:mx-0 sm:h-10 sm:w-10">
              <svg class="h-6 w-6 text-green-600 dark:text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
              </svg>
            </div>
            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
              <h3 class="text-xl font-semibold leading-6 text-slate-900 dark:text-slate-100">Đổi mật khẩu</h3>
              <div class="mt-2">
                <p class="text-sm text-slate-600 dark:text-slate-400">Cập nhật mật khẩu mới cho tài khoản của bạn</p>
              </div>
            </div>
          </div>

          <form id="changePasswordForm" method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="space-y-4">
              <div>
                <label for="current_password" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                  Mật khẩu hiện tại <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input type="password" id="current_password" name="current_password" required
                    class="w-full px-4 py-3 pr-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-green-500 focus:ring-2 focus:ring-green-200 dark:focus:ring-green-800 transition-all duration-200 @error('current_password') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
                    placeholder="Nhập mật khẩu hiện tại">
                  <button type="button" onclick="togglePassword('current_password')" class="absolute inset-y-0 right-0 px-3 flex items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600">
                    <svg id="current_password_eye" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 4.5C7.305 4.5 3.334 7.364 1.5 12c1.834 4.636 5.805 7.5 10.5 7.5S20.666 16.636 22.5 12C20.666 7.364 16.695 4.5 12 4.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                    </svg>
                  </button>
                </div>
                @error('current_password')
                  <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ $message }}
                  </p>
                @enderror
              </div>

              <div>
                <label for="password" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                  Mật khẩu mới <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input type="password" id="password" name="password" required
                    class="w-full px-4 py-3 pr-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-green-500 focus:ring-2 focus:ring-green-200 dark:focus:ring-green-800 transition-all duration-200 @error('password') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
                    placeholder="Nhập mật khẩu mới">
                  <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 px-3 flex items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600">
                    <svg id="password_eye" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 4.5C7.305 4.5 3.334 7.364 1.5 12c1.834 4.636 5.805 7.5 10.5 7.5S20.666 16.636 22.5 12C20.666 7.364 16.695 4.5 12 4.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                    </svg>
                  </button>
                </div>
                @error('password')
                  <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ $message }}
                  </p>
                @enderror
              </div>

              <div>
                <label for="password_confirmation" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                  Xác nhận mật khẩu mới <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                  <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="w-full px-4 py-3 pr-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-green-500 focus:ring-2 focus:ring-green-200 dark:focus:ring-green-800 transition-all duration-200 @error('password_confirmation') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
                    placeholder="Nhập lại mật khẩu mới">
                  <button type="button" onclick="togglePassword('password_confirmation')" class="absolute inset-y-0 right-0 px-3 flex items-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-600">
                    <svg id="password_confirmation_eye" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 4.5C7.305 4.5 3.334 7.364 1.5 12c1.834 4.636 5.805 7.5 10.5 7.5S20.666 16.636 22.5 12C20.666 7.364 16.695 4.5 12 4.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>
                    </svg>
                  </button>
                </div>
                @error('password_confirmation')
                  <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    {{ $message }}
                  </p>
                @enderror
              </div>
            </div>

            <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800">
              <div class="flex items-start">
                <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <div>
                  <h4 class="text-sm font-semibold text-amber-900 dark:text-amber-100 mb-1">Lưu ý bảo mật:</h4>
                  <ul class="text-sm text-amber-800 dark:text-amber-200 space-y-1">
                    <li>• Mật khẩu phải có ít nhất 8 ký tự</li>
                    <li>• Nên bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt</li>
                    <li>• Không sử dụng thông tin cá nhân dễ đoán</li>
                  </ul>
                </div>
              </div>
            </div>
          </form>
        </div>

        <div class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse sm:space-x-2 sm:space-x-reverse sm:px-8 dark:bg-slate-800">
          <button type="submit" form="changePasswordForm" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white hover:from-green-700 hover:to-emerald-700 focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800 transition-all duration-200 font-medium shadow-lg hover:shadow-xl">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Cập nhật mật khẩu
          </button>
          <button type="button" onclick="closeChangePasswordModal()" class="mt-3 inline-flex items-center justify-center px-6 py-3 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 transition-all duration-200 font-medium sm:mt-0">
            Hủy bỏ
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Profile Modal Functions
function openProfileModal() {
  console.log('Opening profile modal...');
  document.getElementById('profileModal').classList.remove('hidden');
  
  // Khởi tạo xử lý form submit cho profile
  const profileForm = document.getElementById('profileForm');
  if (profileForm && !profileForm.hasAttribute('data-profile-initialized')) {
    console.log('Initializing profile form...');
    profileForm.setAttribute('data-profile-initialized', 'true');
    profileForm.addEventListener('submit', function(e) {
      e.preventDefault();
      console.log('Profile form submitted...');

      const formData = new FormData(this);
      // Tìm button submit bên ngoài form (sử dụng form attribute)
      const submitButton = document.querySelector('button[form="' + this.id + '"]');
      if (!submitButton) {
        console.error('Submit button not found for form:', this.id);
        showNotification('Lỗi: Không tìm thấy nút submit', 'error');
        return;
      }
      const originalText = submitButton.innerHTML;

      // Hiển thị loading
      submitButton.innerHTML = `
        <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Đang cập nhật...
      `;
      submitButton.disabled = true;

      fetch(this.action, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          'Accept': 'application/json',
        },
        body: formData,
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          showNotification('Cập nhật thông tin cá nhân thành công!', 'success');
          closeProfileModal();
          // Cập nhật tên hiển thị trên topbar
          location.reload(); // Reload để cập nhật tên mới
        } else {
          showNotification('Lỗi: ' + (data.message || 'Có lỗi xảy ra khi cập nhật'), 'error');
        }
      })
      .catch(error => {
        console.error('Error updating profile:', error);
        showNotification('Có lỗi xảy ra khi cập nhật thông tin cá nhân. Vui lòng thử lại.', 'error');
      })
      .finally(() => {
        // Khôi phục button
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
      });
    });
  }
}

function closeProfileModal() {
  document.getElementById('profileModal').classList.add('hidden');
}

// Change Password Modal Functions
function openChangePasswordModal() {
  console.log('Opening change password modal...');
  document.getElementById('changePasswordModal').classList.remove('hidden');
  
  // Khởi tạo xử lý form submit cho đổi mật khẩu
  const passwordForm = document.getElementById('changePasswordForm');
  if (passwordForm && !passwordForm.hasAttribute('data-password-initialized')) {
    console.log('Initializing password form...');
    passwordForm.setAttribute('data-password-initialized', 'true');
    passwordForm.addEventListener('submit', function(e) {
      e.preventDefault();
      console.log('Password form submitted...');

      const formData = new FormData(this);
      // Tìm button submit bên ngoài form (sử dụng form attribute)
      const submitButton = document.querySelector('button[form="' + this.id + '"]');
      if (!submitButton) {
        console.error('Submit button not found for form:', this.id);
        showNotification('Lỗi: Không tìm thấy nút submit', 'error');
        return;
      }
      const originalText = submitButton.innerHTML;

      // Hiển thị loading
      submitButton.innerHTML = `
        <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Đang cập nhật...
      `;
      submitButton.disabled = true;

      const csrfToken = document.querySelector('meta[name="csrf-token"]');
      if (!csrfToken) {
        console.error('CSRF token not found!');
        showNotification('Lỗi: CSRF token không tìm thấy', 'error');
        return;
      }

      console.log('Sending password request to:', this.action);
      console.log('CSRF token:', csrfToken.getAttribute('content'));

      fetch(this.action, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken.getAttribute('content'),
          'Accept': 'application/json',
        },
        body: formData,
      })
      .then(response => {
        console.log('Password response status:', response.status);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Password response data:', data);
        if (data.success) {
          showNotification('Cập nhật mật khẩu thành công!', 'success');
          closeChangePasswordModal();
          // Reset form
          this.reset();
        } else {
          showNotification('Lỗi: ' + (data.message || 'Có lỗi xảy ra khi cập nhật mật khẩu'), 'error');
        }
      })
      .catch(error => {
        console.error('Error updating password:', error);
        showNotification('Có lỗi xảy ra khi cập nhật mật khẩu. Vui lòng thử lại.', 'error');
      })
      .finally(() => {
        // Khôi phục button
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
      });
    });
  }
}

function closeChangePasswordModal() {
  document.getElementById('changePasswordModal').classList.add('hidden');
}

// Toggle password visibility
function togglePassword(inputId) {
  const input = document.getElementById(inputId);
  const eye = document.getElementById(inputId + '_eye');
  
  if (input.type === 'password') {
    input.type = 'text';
    eye.innerHTML = '<path d="M3 3l18 18-1.5 1.5L17.3 20.3A11.3 11.3 0 0112 21C7.305 21 3.334 18.136 1.5 13.5a12.95 12.95 0 013.77-5.22L1.5 3 3 1.5 22.5 21 21 22.5 17.3 18.8"/>';
  } else {
    input.type = 'password';
    eye.innerHTML = '<path d="M12 4.5C7.305 4.5 3.334 7.364 1.5 12c1.834 4.636 5.805 7.5 10.5 7.5S20.666 16.636 22.5 12C20.666 7.364 16.695 4.5 12 4.5zm0 12a4.5 4.5 0 110-9 4.5 4.5 0 010 9z"/>';
  }
}

// Notification function
function showNotification(message, type = 'info') {
  // Xóa thông báo cũ nếu có
  const existingNotifications = document.querySelectorAll('[data-notification]');
  existingNotifications.forEach(notification => notification.remove());

  const notification = document.createElement('div');
  notification.setAttribute('data-notification', 'true');
  notification.className = `fixed top-4 right-4 z-[100] p-4 rounded-xl shadow-xl border transition-all duration-500 ease-out transform translate-x-full max-w-sm backdrop-blur-sm ${
    type === 'warning' ? 'bg-yellow-50/95 text-yellow-800 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-200 dark:border-yellow-700' :
    type === 'error' ? 'bg-red-50/95 text-red-800 border-red-200 dark:bg-red-900/30 dark:text-red-200 dark:border-red-700' :
    type === 'success' ? 'bg-green-50/95 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-200 dark:border-green-700' :
    'bg-blue-50/95 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-200 dark:border-blue-700'
  }`;

  // Icon theo loại thông báo
  const iconMap = {
    success: '<svg class="w-5 h-5 mr-2 text-green-600 animate-bounce" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>',
    error: '<svg class="w-5 h-5 mr-2 text-red-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>',
    warning: '<svg class="w-5 h-5 mr-2 text-yellow-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
    info: '<svg class="w-5 h-5 mr-2 text-blue-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
  };

  notification.innerHTML = `
    <div class="flex items-start">
      <div class="flex-shrink-0">
        ${iconMap[type] || iconMap.info}
      </div>
      <div class="ml-3 flex-1">
        <p class="text-sm font-medium break-words leading-relaxed">${message}</p>
      </div>
      <div class="ml-4 flex flex-shrink-0">
        <button onclick="this.parentElement.parentElement.parentElement.remove()" class="inline-flex text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 transition-colors rounded-md p-1 hover:bg-slate-100 dark:hover:bg-slate-800">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
          </svg>
        </button>
      </div>
    </div>
  `;

  document.body.appendChild(notification);

  // Hiển thị thông báo với animation
  setTimeout(() => {
    notification.classList.remove('translate-x-full');
    notification.classList.add('translate-x-0');
  }, 100);

  // Tự động ẩn sau 6 giây
  setTimeout(() => {
    if (notification.parentElement) {
      notification.classList.add('translate-x-full');
      notification.classList.remove('translate-x-0');
      setTimeout(() => {
        if (notification.parentElement) {
          notification.remove();
        }
      }, 500);
    }
  }, 6000);
}

// Đóng modal khi click bên ngoài
document.addEventListener('click', function(event) {
  if (event.target.classList.contains('fixed') && event.target.classList.contains('inset-0')) {
    event.target.parentElement.classList.add('hidden');
  }
});

// Debug: Kiểm tra xem các elements có tồn tại không
document.addEventListener('DOMContentLoaded', function() {
  console.log('DOM loaded, checking elements...');
  
  const profileModal = document.getElementById('profileModal');
  const changePasswordModal = document.getElementById('changePasswordModal');
  const profileForm = document.getElementById('profileForm');
  const changePasswordForm = document.getElementById('changePasswordForm');
  
  console.log('Profile modal:', profileModal);
  console.log('Change password modal:', changePasswordModal);
  console.log('Profile form:', profileForm);
  console.log('Change password form:', changePasswordForm);
  
  // Kiểm tra CSRF token
  const csrfToken = document.querySelector('meta[name="csrf-token"]');
  console.log('CSRF token:', csrfToken);
  if (csrfToken) {
    console.log('CSRF token content:', csrfToken.getAttribute('content'));
  }
});
</script>
