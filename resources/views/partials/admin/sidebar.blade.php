<div class="flex h-full w-full flex-col p-2">
  <!-- Brand / collapse button -->
  <div class="mb-2 flex items-center justify-between px-2">
    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
      <x-logo class="h-6 w-6" />
      <span class="font-semibold" x-show="!sidebarCollapsed" x-cloak>
        @php
          use App\Helpers\LogoHelper;
          $logo = LogoHelper::getActiveLogo();
        @endphp
        {{ $logo['name'] ?? config('app.name', 'MyApp') }}
      </span>
    </a>
    <button class="rounded-lg p-2 hover:bg-slate-100 dark:hover:bg-slate-800" @click="sidebarCollapsed = !sidebarCollapsed" title="Thu gọn">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
    </button>
  </div>

  <nav class="space-y-1 px-1 text-sm">
   <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
    <x-slot:icon>
      <svg  class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2 7-7 7 7-2 2v8a2 2 0 01-2 2h-3V12H8v10H5a2 2 0 01-2-2v-8z"/>
      </svg>
    </x-slot:icon>
    Bảng điều khiển
  </x-nav-link>
  
  @if(auth()->user()->hasAdminAccess())
  <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
    <x-slot:icon>
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16 14a4 4 0 10-8 0m8 0v1a4 4 0 004 4H4a4 4 0 004-4v-1m8-6a4 4 0 11-8 0 4 4 0 018 0z"/>
      </svg>
    </x-slot:icon>
    Người dùng
  </x-nav-link>
  @endif

  {{-- Profile --}}
  <button onclick="openProfileModal()" class="w-full flex items-center gap-3 rounded-lg px-3 py-2 text-left text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
    <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
    </svg>
    <span x-show="!sidebarCollapsed" x-cloak class="font-medium">Hồ sơ cá nhân</span>
  </button>
  <x-nav-link :href="route('admin.geo.index')" :active="request()->routeIs('admin.geo.*')">
    <x-slot:icon>
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/>
        <path d="M2 12h20"/>
        <path d="M12 2a15.3 15.3 0 0 0 0 20"/>
        <path d="M12 2a15.3 15.3 0 0 1 0 20"/>
      </svg>
    </x-slot:icon>
    Tỉnh thành
  </x-nav-link>
   <x-nav-link :href="route('admin.schools.index')" :active="request()->routeIs('admin.schools.*') || request()->routeIs('admin.school-areas.*')">
    <x-slot:icon>
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V7a2 2 0 00-2-2h-3V3H9v2H6a2 2 0 00-2 2v6m16 0v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4m16 0H4"/>
      </svg>
    </x-slot:icon>
    Trường học
  </x-nav-link>
  <x-nav-link :href="route('admin.documents.index')" :active="request()->routeIs('admin.documents.*')">
    <x-slot:icon>
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h8l4 4v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>
        <path d="M15 3v5h5"/>
      </svg>
    </x-slot:icon>
    Văn bản
  </x-nav-link>
  <x-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*') || request()->routeIs('admin.report-details.*')">
    <x-slot:icon>
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
      </svg>
    </x-slot:icon>
    Quản lý báo cáo
  </x-nav-link>
 
 <x-nav-link :href="route('admin.backups.index')" :active="request()->routeIs('admin.backups.*')  ">
    <x-slot:icon>
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
      </svg>
    </x-slot:icon>
    Backup dữ liệu
  </x-nav-link>

  <div class="pt-2">
    <div class="text-xs uppercase tracking-wider text-slate-400 px-3 mb-1">Hệ thống</div>

    {{-- Nhóm menu: Cài đặt --}}
    <div
      x-data="{ open: {{ request()->routeIs('admin.settings.*') ? 'true' : 'false' }} }"
      class="space-y-1"
    >
      <button
        type="button"
        @click="open = !open"
        :aria-expanded="open"
        class="w-full flex items-center justify-between rounded-lg px-3 py-2 text-left
              hover:bg-slate-100 dark:hover:bg-slate-800
              transition"
        :class="open ? 'bg-slate-100 dark:bg-slate-800' : ''"
        title="Cài đặt"
      >
        <span class="inline-flex items-center gap-3">
          {{-- icon bánh răng --}}
           <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V7a2 2 0 00-2-2h-3V3H9v2H6a2 2 0 00-2 2v6m16 0v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4m16 0H4"/>
          </svg>

          <span x-show="!sidebarCollapsed" x-cloak class="font-medium">Cài đặt</span>
        </span>

        {{-- mũi tên --}}
        <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-90' : ''"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
      </button>

      {{-- menu con --}}
      <div x-show="open && !sidebarCollapsed" x-transition class="max-h-64 overflow-y-auto">
        <x-nav-link
          :href="route('admin.settings.ethnicities.index')"
          :active="request()->routeIs('admin.settings.ethnicities.*')"
          class="pl-9"
        >
          <x-slot:icon>
            {{-- bullet + dòng: nhìn gọn cho menu con --}}
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Dân tộc
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.religions.index')"
          :active="request()->routeIs('admin.settings.religions.*')"
          class="pl-9"
        >
          <x-slot:icon>
             <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Tôn giáo
        </x-nav-link>
        <x-nav-link
          :href="route('admin.settings.issuers.index')"
          :active="request()->routeIs('admin.settings.issuers.*')"
          class="pl-9"
        >
          <x-slot:icon>
            {{-- icon “con dấu / building” --}}
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="8" rx="2" ry="2"/>
              <path d="M7 11V9a5 5 0 0 1 10 0v2"/>
            </svg>
          </x-slot:icon>
          Đơn vị cấp văn bản
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.decimal-formats.index')"
          :active="request()->routeIs('admin.settings.decimal-formats.*')"
          class="pl-9"
        >
          <x-slot:icon>
            {{-- icon số thập phân --}}
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M8 12h8"/>
              <path d="M12 8v8"/>
              <path d="M3 12h1"/>
              <path d="M20 12h1"/>
              <path d="M12 3v1"/>
              <path d="M12 20v1"/>
            </svg>
          </x-slot:icon>
          Hiển thị thập phân
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.priority-categories.index')"
          :active="request()->routeIs('admin.settings.priority-categories.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Diện ưu tiên
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.residence-types.index')"
          :active="request()->routeIs('admin.settings.residence-types.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Diện cư trú
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.residence-statuses.index')"
          :active="request()->routeIs('admin.settings.residence-statuses.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Tình trạng cư trú
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.education-blocks.index')"
          :active="request()->routeIs('admin.settings.education-blocks.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Khối học
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.graduation-levels.index')"
          :active="request()->routeIs('admin.settings.graduation-levels.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Bậc tốt nghiệp
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.vocational-levels.index')"
          :active="request()->routeIs('admin.settings.vocational-levels.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Bậc tốt nghiệp nghề
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.special-circumstances.index')"
          :active="request()->routeIs('admin.settings.special-circumstances.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="6" cy="12" r="2"/>
              <path d="M11 12h7"/>
            </svg>
          </x-slot:icon>
          Hoàn cảnh đặc biệt
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.pcgd-chi-tieu.index')"
          :active="request()->routeIs('admin.settings.pcgd-chi-tieu.*') || request()->routeIs('admin.settings.chuong-trinh.*') || request()->routeIs('admin.settings.chi-tieu.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
              <rect x="9" y="3" width="6" height="4" rx="2"/>
              <path d="M9 12h6"/>
              <path d="M9 16h6"/>
            </svg>
          </x-slot:icon>
          Giá trị chỉ tiêu PCGD
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.logos.index')"
          :active="request()->routeIs('admin.settings.logos.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
              <circle cx="8.5" cy="8.5" r="1.5"/>
              <path d="M21 15l-5-5L5 21"/>
            </svg>
          </x-slot:icon>
          Logo & Favicon
        </x-nav-link>

        <x-nav-link
          :href="route('admin.settings.guides.index')"
          :active="request()->routeIs('admin.settings.guides.*')"
          class="pl-9"
        >
          <x-slot:icon>
            <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 12l2 2 4-4"/>
              <path d="M21 12c-1 0-3-1-3-3s2-3 3-3 3 1 3 3-2 3-3 3"/>
              <path d="M3 12c1 0 3-1 3-3s-2-3-3-3-3 1-3 3 2 3 3 3"/>
              <path d="M13 12h3"/>
              <path d="M8 12h3"/>
            </svg>
          </x-slot:icon>
          Hướng dẫn
        </x-nav-link>
      </div>
    </div>
  </div>
  </nav>

  <!-- User info -->
  <div class="mt-auto p-2">
    <div class="rounded-lg bg-slate-50 dark:bg-slate-800 p-3">
      <div class="flex items-center gap-3">
        <div class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
          <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 12a4 4 0 100-8 4 4 0 000 8zm7 8H5a5 5 0 019-3h-4a5 5 0 00-5 5"/>
          </svg>
        </div>
        <div class="flex-1 min-w-0" x-show="!sidebarCollapsed" x-cloak>
          <div class="text-sm font-medium text-slate-900 dark:text-slate-100 truncate">
            {{ auth()->user()->name }}
          </div>
          <div class="text-xs text-slate-500 dark:text-slate-400">
            {{ auth()->user()->getRoleDisplayName() }}
          </div>
        </div>
        <button onclick="openProfileModal()" class="rounded-lg p-1 hover:bg-slate-200 dark:hover:bg-slate-700 transition" title="Xem hồ sơ">
          <svg class="h-4 w-4 text-slate-500 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
</div>
