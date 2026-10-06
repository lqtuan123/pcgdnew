@props(['mode' => 'create']) {{-- create | edit --}}

<div class="space-y-8">
  {{-- Thông tin cơ bản --}}
  <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
    <div class="flex items-center mb-6">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30 mr-3">
        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
        </svg>
      </div>
      <div>
        <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Thông tin cơ bản</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400">Nhập thông tin cá nhân của người dùng</p>
      </div>
    </div>
    
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <label for="name" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
          Họ và tên <span class="text-red-500 ml-1">*</span>
        </label>
        <input 
          type="text" 
          id="name" 
          name="name" 
          value="{{ old('name') }}"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 @error('name') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror" 
          placeholder="Nhập họ và tên đầy đủ"
          required
        >
        @error('name')
          <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
          </p>
        @enderror
      </div>

      <div class="sm:col-span-2">
        <label for="email" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
          Email <span class="text-red-500 ml-1">*</span>
        </label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          value="{{ old('email') }}"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 @error('email') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror" 
          placeholder="user@example.com"
          required
        >
        @error('email')
          <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
          </p>
        @enderror
        <div class="mt-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
          <div class="flex items-start">
            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-blue-800 dark:text-blue-200">Email này sẽ được sử dụng để đăng nhập bằng Google OAuth</p>
          </div>
        </div>
      </div>

      <div>
        <label for="phone_number" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
          Số điện thoại
        </label>
        <input 
          type="tel" 
          id="phone_number" 
          name="phone_number" 
          value="{{ old('phone_number') }}"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 @error('phone_number') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror" 
          placeholder="0123 456 789"
        >
        @error('phone_number')
          <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
          </p>
        @enderror
      </div>

      <div>
        <label for="role" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
          Quyền truy cập <span class="text-red-500 ml-1">*</span>
        </label>
        <select 
          id="role" 
          name="role" 
          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 appearance-none bg-no-repeat bg-right bg-contain @error('role') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
          style="background-image: url('data:image/svg+xml,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3e%3cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22M6 8l4 4 4-4%22/%3e%3c/svg%3e')"
          required
        >
          <option value="">Chọn quyền truy cập</option>
          <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>Người dùng</option>
          @if(auth()->user()->canGrantRole('admin'))
            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Quản trị viên</option>
          @endif
        </select>
        @error('role')
          <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
          </p>
        @enderror
      </div>
    </div>
    
    <div class="mt-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-200 dark:border-blue-800">
      <div class="flex items-start">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <div>
          <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">Lưu ý về quyền:</h4>
          <ul class="space-y-1 text-sm text-blue-800 dark:text-blue-200">
            <li class="flex items-center">
              <span class="w-1.5 h-1.5 bg-blue-600 rounded-full mr-2"></span>
              <strong>Người dùng:</strong> Đăng nhập Google, truy cập trang chủ
            </li>
            <li class="flex items-center">
              <span class="w-1.5 h-1.5 bg-blue-600 rounded-full mr-2"></span>
              <strong>Quản trị viên:</strong> Quyền admin, quản lý người dùng
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  {{-- Phân quyền địa giới --}}
  <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
    <div class="flex items-center mb-6">
      <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30 mr-3">
        <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-1.447-.894L15 4m0 13V4m0 0L9 7"/>
        </svg>
      </div>
      <div>
        <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Phân quyền địa giới</h3>
        <p class="text-sm text-slate-600 dark:text-slate-400">Truy cập thông tin trong phạm vi địa giới được chỉ định</p>
      </div>
    </div>

    <div class="space-y-6">
      <div>
        <label for="geo_scheme" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
          Hệ thống địa giới
        </label>
        <select 
          id="geo_scheme" 
          name="geo_scheme"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-green-500 focus:ring-2 focus:ring-green-200 dark:focus:ring-green-800 transition-all duration-200 appearance-none bg-no-repeat bg-right bg-contain @error('geo_scheme') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
          style="background-image: url('data:image/svg+xml,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3e%3cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22M6 8l4 4 4-4%22/%3e%3c/svg%3e')"
        >
          <option value="">Chọn hệ thống địa giới</option>
          <option value="old" {{ old('geo_scheme') === 'old' ? 'selected' : '' }}>Hệ thống cũ</option>
          <option value="new" {{ old('geo_scheme') === 'new' ? 'selected' : '' }}>Hệ thống mới</option>
        </select>
        @error('geo_scheme')
          <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
          </p>
        @enderror
      </div>

      <div>
        <label for="geo_province" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
          Tỉnh/thành phố <span class="text-red-500 ml-1">*</span>
        </label>
        <select 
          id="geo_province" 
          name="geo_province" 
          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-green-500 focus:ring-2 focus:ring-green-200 dark:focus:ring-green-800 transition-all duration-200 appearance-none bg-no-repeat bg-right bg-contain disabled:bg-slate-100 dark:disabled:bg-slate-600 disabled:text-slate-500 @error('geo_province') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
          style="background-image: url('data:image/svg+xml,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3e%3cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22M6 8l4 4 4-4%22/%3e%3c/svg%3e')"
          required
          disabled
        >
          <option value="">Vui lòng chọn hệ thống địa giới trước</option>
        </select>
        @error('geo_province')
          <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
          </p>
        @enderror
      </div>

      <div id="geo_commune_section" class="hidden">
        <label for="geo_commune" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
          Xã/phường <span class="text-slate-500 text-sm font-normal">(Tùy chọn)</span>
        </label>
        <select 
          id="geo_commune" 
          name="geo_commune"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 focus:border-green-500 focus:ring-2 focus:ring-green-200 dark:focus:ring-green-800 transition-all duration-200 appearance-none bg-no-repeat bg-right bg-contain @error('geo_commune') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
          style="background-image: url('data:image/svg+xml,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3e%3cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22M6 8l4 4 4-4%22/%3e%3c/svg%3e')"
        >
          <option value="">Chọn xã/phường (tùy chọn)</option>
        </select>
        @error('geo_commune')
          <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
          </p>
        @enderror
        <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
          <div class="flex items-start">
            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <p class="text-sm text-amber-800 dark:text-amber-200">Chọn xã/phường để giới hạn phạm vi truy cập</p>
          </div>
        </div>
      </div>

      <div>
        <label for="geo_note" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">
          Ghi chú phân quyền
        </label>
        <textarea 
          id="geo_note" 
          name="geo_note" 
          rows="3"
          class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-green-500 focus:ring-2 focus:ring-green-200 dark:focus:ring-green-800 transition-all duration-200 resize-none @error('geo_note') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
          placeholder="Ghi chú về phân quyền địa giới (tùy chọn)"
        >{{ old('geo_note') }}</textarea>
        @error('geo_note')
          <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            {{ $message }}
          </p>
        @enderror
      </div>

      <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl border border-green-200 dark:border-green-800">
        <div class="flex items-start">
          <svg class="w-5 h-5 text-green-600 dark:text-green-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
          <div>
            <h4 class="text-sm font-semibold text-green-900 dark:text-green-100 mb-2">Lưu ý về phân quyền địa giới:</h4>
            <ul class="space-y-1 text-sm text-green-800 dark:text-green-200">
              <li class="flex items-center">
                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-2"></span>
                <strong>Chỉ tỉnh:</strong> Truy cập tất cả thông tin trong tỉnh
              </li>
              <li class="flex items-center">
                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-2"></span>
                <strong>Tỉnh + xã/phường:</strong> Chỉ truy cập thông tin trong xã/phường
              </li>
              <li class="flex items-center">
                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-2"></span>
                <strong>Hệ thống mới:</strong> Tỉnh → Xã/phường
              </li>
              <li class="flex items-center">
                <span class="w-1.5 h-1.5 bg-green-600 rounded-full mr-2"></span>
                <strong>Hệ thống cũ:</strong> Tỉnh → Huyện → Xã
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
