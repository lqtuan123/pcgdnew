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
  @include('partials.front.navbar')

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
     @if (session('error'))
      <div class="mb-4 rounded-lg border border-red-200 bg-red-400 p-3 text-sm text-black-700">
        {{ session('error') }}
      </div>
    @endif
 @if (session('status'))
      <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
        {{ session('status') }}
      </div>
    @endif
    @yield('content')
  </main>

  {{-- Footer --}}
  @include('partials.front.footer')

  {{-- User Profile Modal --}}
  @auth
  <div id="userProfileModal" class="fixed inset-0 z-[60] hidden">
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

            <form id="userProfileForm" method="POST" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
              @csrf
              @method('PATCH')

              {{-- Thông tin cơ bản --}}
              <div class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label for="user_profile_name" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                      Họ và tên <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="user_profile_name" name="name" value="{{ auth()->user()->name }}" required
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
                    <label for="user_profile_phone" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                      Số điện thoại
                    </label>
                    <input type="tel" id="user_profile_phone" name="phone_number" value="{{ auth()->user()->phone_number }}"
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
                  <label for="user_profile_email" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    Email
                  </label>
                  <input type="email" id="user_profile_email" value="{{ auth()->user()->email }}" disabled
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-600 text-slate-500 dark:text-slate-400 cursor-not-allowed">
                  <p class="mt-2 text-sm text-slate-500 dark:text-slate-400 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    Email được liên kết với tài khoản Google và không thể thay đổi
                  </p>
                </div>
              </div>

              {{-- Thông tin vai trò --}}
              <div class="border-t border-slate-200 pt-6 dark:border-slate-700">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-4">Thông tin vai trò & quyền</h4>
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
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Cấp độ</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                          @if(auth()->user()->isProvinceLevel())
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                              <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                              </svg>
                              Cấp Tỉnh
                            </span>
                          @elseif(auth()->user()->isWardLevel())
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                              <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                              </svg>
                              Cấp Xã/Phường
                            </span>
                          @elseif(auth()->user()->isSchoolLevel())
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                              <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/>
                              </svg>
                              Cấp Trường
                            </span>
                          @else
                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                              <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                              </svg>
                              Chưa xác định
                            </span>
                          @endif
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {{-- Thông tin địa giới quản lý --}}
              <div class="border-t border-slate-200 pt-6 dark:border-slate-700">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-4">
                  <svg class="w-4 h-4 mr-1 inline" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                  </svg>
                  Địa giới quản lý
                </h4>

                @php
                  $roleInfo = auth()->user()->getRoleLevelAndScope();
                @endphp

                <div class="space-y-3">
                  <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
                    <div class="flex items-start">
                      <div class="flex-shrink-0 mt-1">
                        <svg class="h-4 w-4 text-slate-400" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-2 .89-2 2v11c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm6 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1z"/>
                        </svg>
                      </div>
                      <div class="ml-3 flex-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Phạm vi quản lý</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                          {{ $roleInfo['scope_display'] ?? 'Chưa được phân quyền địa giới' }}
                        </p>
                      </div>
                    </div>
                  </div>

                  @if(auth()->user()->province || auth()->user()->ward || auth()->user()->school)
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                      @if(auth()->user()->province)
                        <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
                          <div class="flex items-center">
                            <svg class="h-4 w-4 text-purple-600 dark:text-purple-400 mr-2" fill="currentColor" viewBox="0 0 24 24">
                              <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/>
                            </svg>
                            <div>
                              <p class="text-xs font-medium text-purple-800 dark:text-purple-200">Tỉnh</p>
                              <p class="text-sm font-semibold text-purple-900 dark:text-purple-100">{{ auth()->user()->province->name ?? 'N/A' }}</p>
                            </div>
                          </div>
                        </div>
                      @endif

                      @if(auth()->user()->ward)
                        <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-700">
                          <div class="flex items-center">
                            <svg class="h-4 w-4 text-orange-600 dark:text-orange-400 mr-2" fill="currentColor" viewBox="0 0 24 24">
                              <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                            </svg>
                            <div>
                              <p class="text-xs font-medium text-orange-800 dark:text-orange-200">Xã/Phường</p>
                              <p class="text-sm font-semibold text-orange-900 dark:text-orange-100">{{ auth()->user()->ward->name ?? 'N/A' }}</p>
                            </div>
                          </div>
                        </div>
                      @endif

                      @if(auth()->user()->school)
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
                          <div class="flex items-center">
                            <svg class="h-4 w-4 text-green-600 dark:text-green-400 mr-2" fill="currentColor" viewBox="0 0 24 24">
                              <path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/>
                            </svg>
                            <div>
                              <p class="text-xs font-medium text-green-800 dark:text-green-200">Trường</p>
                              <p class="text-sm font-semibold text-green-900 dark:text-green-100">{{ auth()->user()->school->name ?? 'N/A' }}</p>
                            </div>
                          </div>
                        </div>
                      @endif
                    </div>
                  @else
                    <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-xl text-center">
                      <svg class="h-8 w-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                      </svg>
                      <p class="text-sm text-gray-600 dark:text-gray-400">Chưa được phân quyền địa giới cụ thể</p>
                    </div>
                  @endif
                </div>

                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                  <div class="flex items-start">
                    <svg class="h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <div>
                      <p class="text-xs font-medium text-blue-800 dark:text-blue-200">Ngày tham gia</p>
                      <p class="text-sm text-blue-900 dark:text-blue-100">{{ auth()->user()->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>

          <div class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse sm:space-x-2 sm:space-x-reverse sm:px-8 dark:bg-slate-800">
            <button type="submit" form="userProfileForm" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800 transition-all duration-200 font-medium shadow-lg hover:shadow-xl">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              Cập nhật thông tin
            </button>
            <button type="button" onclick="closeUserProfileModal()" class="mt-3 inline-flex items-center justify-center px-6 py-3 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 transition-all duration-200 font-medium sm:mt-0">
              Đóng
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
  @endauth

  {{-- Nơi view con @push JS cuối trang (khuyến nghị đặt script ở đây) --}}
  @stack('scripts')

  {{-- User Profile Modal Script --}}
  @auth
  <script>
    
    function openUserProfileModal() {
      document.getElementById('userProfileModal').classList.remove('hidden');

      // Khởi tạo xử lý form submit cho profile
      const profileForm = document.getElementById('userProfileForm');
      const submitButton = document.querySelector('button[form="userProfileForm"][type="submit"]');
      
      if (profileForm && submitButton && !profileForm.hasAttribute('data-profile-initialized')) {
        profileForm.setAttribute('data-profile-initialized', 'true');
        
        // Hàm xử lý submit chung
        function handleSubmit(e) {
          e.preventDefault();

          const formData = new FormData(profileForm);
          const originalText = submitButton.innerHTML;

          // Hiển thị loading
          submitButton.innerHTML = `
            <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Đang cập nhật...
          `;
          submitButton.disabled = true;

          fetch(profileForm.action, {
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
              closeUserProfileModal();
              // Cập nhật tên hiển thị trên navbar
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
        }
        
        // Thêm event listener cho cả form và button
        profileForm.addEventListener('submit', handleSubmit);
        submitButton.addEventListener('click', handleSubmit);
      }
    }

    function closeUserProfileModal() {
      document.getElementById('userProfileModal').classList.add('hidden');
    }

    // Đóng modal profile khi click bên ngoài
    document.addEventListener('click', function(event) {
      const userModal = document.getElementById('userProfileModal');
      if (userModal && !userModal.classList.contains('hidden') && event.target === userModal) {
        closeUserProfileModal();
      }
    });

    // Hàm hiển thị thông báo cải tiến
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
  </script>
  @endauth


</body>
</html>
