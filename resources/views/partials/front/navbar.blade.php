<header x-data="{ open: false }" class="sticky top-0 z-40 border-b bg-white/90 backdrop-blur dark:bg-slate-900/80">
    <div class="mx-auto max-w-7xl px-4">
        <div class="flex h-14 items-center justify-between">
            <a href="{{ route('front.home') }}" class="flex items-center gap-2">
                <x-logo class="h-8 w-auto" />
                <span class="font-semibold">
                    @php
                        use App\Helpers\LogoHelper;
                        $logo = LogoHelper::getActiveLogo();
                    @endphp
                    {{ $logo['name'] ?? config('app.name', 'Phổ Cập') }}
                </span>
            </a>

            {{-- Desktop nav --}}
            <nav class="hidden md:flex items-center gap-1 text-sm">
                {{-- Danh mục --}}
                <div class="relative" x-data="{ openDm: false }">
                    <button @click="openDm=!openDm" @keydown.escape="openDm=false" type="button"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        :aria-expanded="openDm" aria-haspopup="true">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6 4h12v2H6zM6 9h12v2H6zM6 14h12v2H6z" />
                        </svg>
                        <span>Danh mục</span>
                        <svg class="h-4 w-4 transition-transform" :class="openDm ? 'rotate-180' : ''"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                        </svg>
                    </button>
                    <div x-cloak x-show="openDm" x-transition @click.away="openDm=false"
                        class="absolute left-0 mt-2 w-64 rounded-xl border bg-white shadow-lg ring-1 ring-black/5 overflow-visible">
                        <a href="{{ route('front.user-management.index') }}"
                            class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" role="menuitem">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm-9 9a9 9 0 0118 0H3z" />
                            </svg>
                            <span>Người dùng</span>
                        </a>
                        <a href="{{ route('front.geo.index') }}"
                            class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" role="menuitem">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1112 6a2.5 2.5 0 010 5.5z" />
                            </svg>
                            <span>Đơn vị hành chính (Geo)</span>
                        </a>
                        <a href="{{ route('front.schools.index') }}"
                            class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" role="menuitem">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M4 10h16v2H4zM6 7h12V5H6v2zm0 10h12v-2H6v2z" />
                            </svg>
                            <span>Danh mục trường</span>
                        </a>
            {{-- thêm dưới "Đơn vị hành chính (Geo)" --}}
            <a href="{{ route('front.ethnicities.index') }}" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" role="menuitem">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zM3 20a9 9 0 0118 0H3z"/></svg>
              <span>Dân tộc</span>
            </a>

            <a href="{{ route('front.religions.index') }}" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l7 7-7 7-7-7 7-7z"/></svg>
              <span>Tôn giáo</span>
            </a>
             <a href="{{ route('front.documents.index') }}" class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50">
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M14 2H7a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V9z"/>
                  <path d="M14 2v7h7"/>
                  <path d="M9 13h8"/>
                  <path d="M9 17h8"/>
                </svg>
              <span>Văn bản</span>
            </a>

                        {{-- Submenu Cấu hình --}}
                        <div class="relative" x-data="{ openCf: false }">
                            <button @click.stop="openCf=!openCf" @keydown.escape="openCf=false" type="button"
                                class="flex w-full items-center gap-2 px-3 py-2 hover:bg-slate-50 focus:outline-none"
                                :aria-expanded="openCf" aria-haspopup="true">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                                    <path
                                        d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
                                </svg>
                                <span>Cấu hình</span>
                                <svg class="h-4 w-4 transition-transform" :class="openCf ? 'rotate-180' : ''"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                                </svg>
                            </button>
                            <div x-cloak x-show="openCf" x-transition @click.stop @click.away="openCf=false"
                                class="absolute left-full top-0 ml-1 w-48 rounded-xl border bg-white shadow-lg ring-1 ring-black/5 overflow-hidden z-50">
                                <a href="{{ route('front.decimal-format.index') }}"
                                    class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" role="menuitem">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                    </svg>
                                    <span>Phân tách thập phân</span>
                                </a>
                                <a href="{{ route('front.pcgd-chi-tieu.index') }}"
                                    class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" role="menuitem">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    </svg>
                                    <span>Chỉ tiêu PCGD</span>
                                </a>
                                <a href="{{ route('front.report-config.index') }}"
                                    class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" role="menuitem">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                                    </svg>
                                    <span>Báo cáo</span>
                                </a>
                                {{-- <a href="{{ route('front.statistical-formula.index') }}"
                                    class="flex items-center gap-2 px-3 py-2 hover:bg-slate-50" role="menuitem">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2.5 2.5L15 15l3.5-3.5L15 8l-3.5 3.5L8 8l-3.5 3.5L8 15l-3.5 3.5L8 22l3.5-3.5L15 22l3.5-3.5z"/>
                                    </svg>
                                    <span>Công thức thống kê</span>
                                </a> --}}
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Điều tra --}}
                <div class="relative" x-data="{ openDt: false }">
                    <button @click="openDt=!openDt" @keydown.escape="openDt=false"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4 6h16v2H4zM4 11h16v2H4zM4 16h10v2H4z" />
                        </svg>
                        <span>Điều tra</span>
                        <svg class="h-4 w-4 transition-transform" :class="openDt ? 'rotate-180' : ''"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                        </svg>
                    </button>
                    <div x-cloak x-show="openDt" x-transition @click.away="openDt=false"
                        class="absolute left-0 mt-2 w-64 rounded-xl border bg-white shadow-lg ring-1 ring-black/5 overflow-hidden">
                        <a href="{{ route('front.surveyforms.index') }}" class="block px-3 py-2 hover:bg-slate-50">Danh sách phiếu điều tra
                            </a>
                        <a href="{{ route('front.surveys.index') }}" class="block px-3 py-2 hover:bg-slate-50">Danh sách đối tượng điều tra
                            </a>
                        <a href="{{ route('front.surveys.create') }}" class="block px-3 py-2 hover:bg-slate-50">Tạo khảo
                            sát</a>
                        <a href="{{ route('front.surveys.deleted') }}" class="block px-3 py-2 hover:bg-slate-50">Danh sách xóa tạm
                            </a>
                        <div class="border-t border-slate-200 my-1"></div>
                        <a href="{{ route('front.preschool-students.index') }}" class="block px-3 py-2 hover:bg-slate-50">Học sinh mầm non</a>
                        <a href="{{ route('front.students-off-track.index') }}" class="block px-3 py-2 hover:bg-slate-50">Học sinh trái tuyến</a>
                        <div class="border-t border-slate-200 my-1"></div>
                        <a href="{{ route('front.data-validation.index') }}" class="block px-3 py-2 hover:bg-slate-50">Kiểm tra dữ liệu</a>
                        <a href="{{ route('front.students-off-track.level-transfer.index') }}" class="block px-3 py-2 hover:bg-slate-50">Lên lớp học sinh trái tuyến</a>
                        <a href="{{ route('front.level-transfer.index') }}" class="block px-3 py-2 hover:bg-slate-50">Thực hiện lên lớp</a>
                        <div class="border-t border-slate-200 my-1"></div>
                        <a href="{{ route('front.surveylocks.index') }}" class="block px-3 py-2 hover:bg-slate-50">Khóa dữ liệu</a>
                     
                    
                    
                    </div>
                </div>

                {{-- Thống kê --}}
                <div class="relative" x-data="{ openTk: false }">
                    <button @click="openTk=!openTk" @keydown.escape="openTk=false"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3 13h4v8H3zM10 3h4v18h-4zM17 8h4v13h-4z" />
                        </svg>
                        <span>Thống kê</span>
                        <svg class="h-4 w-4 transition-transform" :class="openTk ? 'rotate-180' : ''"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                        </svg>
                    </button>
                    <div x-cloak x-show="openTk" x-transition @click.away="openTk=false"
                        class="absolute left-0 mt-2 w-64 rounded-xl border bg-white shadow-lg ring-1 ring-black/5 overflow-hidden">
                        <a href="{{ route('front.reports.search.index') }}" class="block px-3 py-2 hover:bg-slate-50">
                            <svg class="inline h-4 w-4 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                            Tra cứu thống kê
                        </a>
                        <a href="{{ route('front.reports.search.management.index') }}" class="block px-3 py-2 hover:bg-slate-50">
                            <svg class="inline h-4 w-4 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                            </svg>
                            Quản lý báo cáo
                        </a>
                        <a href="{{ route('front.reports.statistical-verification') }}" class="block px-3 py-2 hover:bg-slate-50">
                            <svg class="inline h-4 w-4 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2 2H5V5h14v14z"/>
                            </svg>
                            So dò thống kê
                        </a>
                        <a href="{{ route('front.reports.statistical-check') }}" class="block px-3 py-2 hover:bg-slate-50">
                            <svg class="inline h-4 w-4 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M3 13h4v8H3zM10 3h4v18h-4zM17 8h4v13h-4z" />
                            </svg>
                            Kiểm tra thống kê
                        </a>
                        @auth
                            @if(auth()->user()->isSuperadmin() || (auth()->user()->province_id && auth()->user()->ward_id && !auth()->user()->school_id))
                                <div class="border-t border-slate-200 my-1"></div>
                                <a href="{{ route('gdmn-finance.index') }}" class="block px-3 py-2 hover:bg-slate-50">
                                    <svg class="inline h-4 w-4 mr-2" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                    Tình hình tài chính Mầm non
                                </a>
                            @endif
                        @endauth
                    </div>
                </div>

                {{-- Báo cáo --}}
                <div class="relative" x-data="{ openBc: false }">
                    <button @click="openBc=!openBc" @keydown.escape="openBc=false"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7 4h10v2H7zM7 8h10v2H7zM7 12h10v2H7zM7 16h10v2H7z" />
                        </svg>
                        <span>Báo cáo</span>
                        <svg class="h-4 w-4 transition-transform" :class="openBc ? 'rotate-180' : ''"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path
                                d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                        </svg>
                    </button>
                    <div x-cloak x-show="openBc" x-transition @click.away="openBc=false"
                        class="absolute left-0 mt-2 w-64 rounded-xl border bg-white shadow-lg ring-1 ring-black/5 overflow-hidden">
                        <a href="{{ route('front.pcgd-report.index') }}" class="block px-3 py-2 hover:bg-slate-50">Báo cáo kết quả PCGD</a>
                        <a href="{{ route('front.pcgd-xmc-report.index') }}" class="block px-3 py-2 hover:bg-slate-50">Biên bản kiểm tra kết quả PCGD-XMC</a>
                    </div>
                </div>

                {{-- Hướng dẫn --}}
                <a href="{{ route('front.guide.index') }}" 
                   class="inline-flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span>Hướng dẫn</span>
                </a>

                @auth
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
                            <button onclick="openUserProfileModal()" class="w-full text-left flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
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
                            
                            <div class="border-t border-slate-200 dark:border-slate-700 my-2"></div>
                            
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 transition">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Đăng xuất
                                </button>
                            </form>
                        </div>
                    </div>
                    <a href="{{ route('admin.dashboard') }}" class="btn-primary ml-2">Vào quản trị</a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary ml-2">Đăng nhập quản trị</a>
                @endauth
            </nav>

            {{-- Button mở menu mobile --}}
            <button class="md:hidden rounded-lg p-2 hover:bg-slate-100" @click="open=!open" aria-label="Menu">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z" />
                </svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div x-cloak x-show="open" @click.away="open=false" class="md:hidden pb-3">
            <div x-data="{ dm: false, dt: false, tk: false, bc: false }" class="grid gap-1 text-sm">
                <button @click="dm=!dm"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-slate-100">
                    <span>Danh mục</span>
                    <svg class="h-4 w-4 transition-transform" :class="dm ? 'rotate-180' : ''" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                    </svg>
                </button>
                <div x-cloak x-show="dm" class="ml-3 grid">
                    <a href="{{ route('front.user-management.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Người
                        dùng</a>
                    <a href="{{ route('front.geo.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Đơn vị
                        hành chính (Geo)</a>
                    {{-- THÊM DÒNG NÀY --}}
                    <a href="{{ route('front.schools.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Danh
                        mục trường</a>
          <a href="{{ route('front.ethnicities.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Dân tộc</a>
          <a href="{{ route('front.religions.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Tôn giáo</a>
          <a href="{{ route('front.documents.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Tài liệu</a>



                    {{-- Submenu Cấu hình Mobile --}}
                    <div x-data="{ cf: false }" class="space-y-1">
                        <button @click.stop="cf=!cf"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-slate-100">
                            <span>Cấu hình</span>
                            <svg class="h-4 w-4 transition-transform" :class="cf ? 'rotate-180' : ''"
                                viewBox="0 0 20 20" fill="currentColor">
                                <path
                                    d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                            </svg>
                        </button>
                        <div x-cloak x-show="cf" class="ml-3 grid">
                            <a href="{{ route('front.decimal-format.index') }}"
                                class="rounded-lg px-3 py-2 hover:bg-slate-100">Phân tách thập phân</a>
                            <a href="{{ route('front.pcgd-chi-tieu.index') }}"
                                class="rounded-lg px-3 py-2 hover:bg-slate-100">Chỉ tiêu PCGD</a>
                            <a href="{{ route('front.report-config.index') }}"
                                class="rounded-lg px-3 py-2 hover:bg-slate-100">Báo cáo</a>
                            {{-- <a href="{{ route('front.statistical-formula.index') }}"
                                class="rounded-lg px-3 py-2 hover:bg-slate-100">Công thức thống kê</a> --}}
                        </div>
                    </div>

                </div>

                <button @click="dt=!dt"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-slate-100">
                    <span>Điều tra</span>
                    <svg class="h-4 w-4 transition-transform" :class="dt ? 'rotate-180' : ''" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                    </svg>
                </button>
                <div x-cloak x-show="dt" class="ml-3 grid">
                    <a href="{{ route('front.surveys.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Danh
                        sách khảo sát</a>
                    <a href="{{ route('front.surveys.create') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Tạo
                        khảo sát</a>
                    <a href="{{ route('front.surveys.deleted') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Danh sách xóa tạm</a>
                    <div class="border-t border-slate-200 my-1"></div>
                    <a href="{{ route('front.preschool-students.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Học sinh mầm non</a>
                    <a href="{{ route('front.students-off-track.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Học sinh trái tuyến</a>
                    <div class="border-t border-slate-200 my-1"></div>
                    <a href="{{ route('front.data-validation.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Kiểm tra dữ liệu</a>
                    <a href="{{ route('front.students-off-track.level-transfer.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Lên lớp học sinh trái tuyến</a>
                    <a href="{{ route('front.level-transfer.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Thực hiện lên lớp</a>
                </div>

                <button @click="tk=!tk"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-slate-100">
                    <span>Thống kê</span>
                    <svg class="h-4 w-4 transition-transform" :class="tk ? 'rotate-180' : ''" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                    </svg>
                </button>
                <div x-cloak x-show="tk" class="ml-3 grid">
                    <a href="{{ route('front.reports.search.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Tra cứu thống kê</a>
                    <a href="{{ route('front.reports.search.management.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Quản lý báo cáo</a>
                    <a href="{{ route('front.reports.statistical-verification') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">So dò thống kê</a>
                    <a href="{{ route('front.reports.statistical-check') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Kiểm tra thống kê</a>
                    @auth
                        @if(auth()->user()->isSuperadmin() || (auth()->user()->province_id && auth()->user()->ward_id && !auth()->user()->school_id))
                            <div class="border-t border-slate-200 my-1"></div>
                            <a href="{{ route('gdmn-finance.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100">Tình hình tài chính Mầm non</a>
                        @endif
                    @endauth
                </div>

                <button @click="bc=!bc"
                    class="flex w-full items-center justify-between rounded-lg px-3 py-2 hover:bg-slate-100">
                    <span>Báo cáo</span>
                    <svg class="h-4 w-4 transition-transform" :class="bc ? 'rotate-180' : ''" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.24a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" />
                    </svg>
                </button>
                <div x-cloak x-show="bc" class="ml-3 grid">
                    <a href="{{ route('front.pcgd-report.index') }}"
                        class="rounded-lg px-3 py-2 hover:bg-slate-100">Báo cáo kết quả PCGD</a>
                    <a href="{{ route('front.reports.export') }}"
                        class="rounded-lg px-3 py-2 hover:bg-slate-100">Biên bản kiểm tra kết quả PCGD-XMC</a>
                </div>

                {{-- Hướng dẫn Mobile --}}
                <a href="{{ route('front.guide.index') }}" class="rounded-lg px-3 py-2 hover:bg-slate-100 flex items-center gap-2">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Hướng dẫn
                </a>

                @auth
                    <div class="border-t border-slate-200 dark:border-slate-700 my-2"></div>
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
                    
                    <button onclick="openUserProfileModal()"
                        class="w-full text-left rounded-lg px-3 py-2 hover:bg-slate-100 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Hồ sơ cá nhân
                    </button>
                    
                    <button onclick="openChangePasswordModal()" class="w-full text-left rounded-lg px-3 py-2 hover:bg-slate-100 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        Đổi mật khẩu
                    </button>
                    
                    <a href="{{ route('admin.dashboard') }}" class="btn-primary mt-1">Vào quản trị</a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary mt-1">Đăng nhập quản trị</a>
                @endauth
            </div>
        </div>
    </div>
</header>

{{-- Modal Đổi Mật Khẩu --}}
<div id="changePasswordModal" class="fixed inset-0 z-[60] hidden">
    <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity z-[60]"></div>
    
    <div class="fixed inset-0 z-[70] overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl border bg-white shadow-xl ring-1 ring-black/5 transition-all sm:my-8 sm:w-full sm:max-w-md dark:bg-slate-900 dark:border-slate-800">
                <div class="bg-white px-6 pt-6 pb-4 sm:p-8 dark:bg-slate-900">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

                    <form id="changePasswordForm" method="POST" action="{{ route('profile.password.update') }}" class="mt-6 space-y-6">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="modal_current_password" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                Mật khẩu hiện tại <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="modal_current_password" name="current_password" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 @error('current_password') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
                                placeholder="Nhập mật khẩu hiện tại">
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
                            <label for="modal_new_password" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                Mật khẩu mới <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="modal_new_password" name="password" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 @error('password') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
                                placeholder="Nhập mật khẩu mới">
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
                            <label for="modal_password_confirmation" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                Xác nhận mật khẩu mới <span class="text-red-500">*</span>
                            </label>
                            <input type="password" id="modal_password_confirmation" name="password_confirmation" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:focus:ring-blue-800 transition-all duration-200 @error('password_confirmation') border-red-400 focus:border-red-500 focus:ring-red-200 @enderror"
                                placeholder="Nhập lại mật khẩu mới">
                            @error('password_confirmation')
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </form>
                </div>

                <div class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse sm:space-x-2 sm:space-x-reverse sm:px-8 dark:bg-slate-800">
                    <button type="submit" form="changePasswordForm" class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800 transition-all duration-200 font-medium shadow-lg hover:shadow-xl">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Đổi mật khẩu
                    </button>
                    <button type="button" onclick="closeChangePasswordModal()" class="mt-3 inline-flex items-center justify-center px-6 py-3 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-700 hover:bg-slate-50 dark:hover:bg-slate-600 transition-all duration-200 font-medium sm:mt-0">
                        Hủy
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.remove('hidden');
        
        // Khởi tạo xử lý form submit
        const changePasswordForm = document.getElementById('changePasswordForm');
        const submitButton = document.querySelector('button[form="changePasswordForm"][type="submit"]');
        
        if (changePasswordForm && submitButton && !changePasswordForm.hasAttribute('data-password-initialized')) {
            changePasswordForm.setAttribute('data-password-initialized', 'true');
            
            // Hàm xử lý submit
            function handlePasswordSubmit(e) {
                e.preventDefault();
                
                const formData = new FormData(changePasswordForm);
                const originalText = submitButton.innerHTML;
                
                // Hiển thị loading
                submitButton.innerHTML = `
                    <svg class="w-4 h-4 mr-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Đang đổi mật khẩu...
                `;
                submitButton.disabled = true;
                
                fetch(changePasswordForm.action, {
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
                        showNotification('Đổi mật khẩu thành công!', 'success');
                        closeChangePasswordModal();
                        // Xóa form
                        changePasswordForm.reset();
                    } else {
                        showNotification('Lỗi: ' + (data.message || 'Có lỗi xảy ra khi đổi mật khẩu'), 'error');
                    }
                })
                .catch(error => {
                    console.error('Error changing password:', error);
                    showNotification('Có lỗi xảy ra khi đổi mật khẩu. Vui lòng thử lại.', 'error');
                })
                .finally(() => {
                    // Khôi phục button
                    submitButton.innerHTML = originalText;
                    submitButton.disabled = false;
                });
            }
            
            // Thêm event listener
            changePasswordForm.addEventListener('submit', handlePasswordSubmit);
            submitButton.addEventListener('click', handlePasswordSubmit);
        }
    }
    
    function closeChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.add('hidden');
        // Reset form
        document.getElementById('changePasswordForm').reset();
    }
    
    // Đóng modal khi click bên ngoài
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('fixed') && event.target.classList.contains('inset-0')) {
            if (event.target.id === 'changePasswordModal') {
                closeChangePasswordModal();
            }
        }
    });
    
    // Hàm hiển thị thông báo
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
