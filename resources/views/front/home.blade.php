@extends('layouts.front')
@section('title', 'Trang chủ')

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('content')



    {{-- THỐNG KÊ NGƯỜI DÙNG (chỉ hiển thị cho admin) --}}
    {{-- @auth
    @if (auth()->user()->hasAdminAccess())
      <section class="mx-auto max-w-7xl px-4 pb-10">
        <h2 class="text-lg font-semibold mb-3">Thống kê hệ thống</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <article class="kpi">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm text-slate-500">Tổng người dùng</div>
                <div class="mt-1 text-2xl font-semibold">{{ \App\Models\User::count() }}</div>
              </div>
              <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zm7 8H5a5 5 0 019-3h-4a5 5 0 00-5 5"/></svg>
              </div>
            </div>
            <div class="mt-3 text-xs text-emerald-600">Tất cả người dùng</div>
          </article>

          <article class="kpi">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm text-slate-500">Người dùng Google</div>
                <div class="mt-1 text-2xl font-semibold">{{ \App\Models\User::where('role', 'user')->count() }}</div>
              </div>
              <div class="h-10 w-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/></svg>
              </div>
            </div>
            <div class="mt-3 text-xs text-emerald-600">Đăng nhập bằng Google</div>
          </article>

          <article class="kpi">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm text-slate-500">Quản trị viên</div>
                <div class="mt-1 text-2xl font-semibold">{{ \App\Models\User::where('role', 'admin')->count() }}</div>
              </div>
              <div class="h-10 w-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              </div>
            </div>
            <div class="mt-3 text-xs text-emerald-600">Có quyền quản trị</div>
          </article>

          <article class="kpi">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm text-slate-500">Tổng trường học</div>
                <div class="mt-1 text-2xl font-semibold">{{ \App\Models\School::count() }}</div>
              </div>
              <div class="h-10 w-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
              </div>
            </div>
            <div class="mt-3 text-xs text-emerald-600">Trong hệ thống</div>
          </article>
        </div>
      </section>
    @endif
  @endauth --}}

    {{-- MENU CHỨC NĂNG THEO CỤM --}}
    <section class="mx-auto max-w-7xl px-4 pt-4 pb-10">
        <div class="mb-4 flex items-center justify-between py-3">
            <h1 class="text-xl font-semibold">Chức năng hệ thống</h1>
        </div>
        
        {{-- CỤM DANH MỤC --}}
        <div class="mb-8">
            <h3 class="text-base font-medium text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6 4h12v2H6zM6 9h12v2H6zM6 14h12v2H6z" />
                </svg>
                Danh mục
            </h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Người dùng --}}
                <a href="{{ route('front.user-management.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zM3 21a9 9 0 0118 0H3z" />
                        </svg>
                    </div>
                    <div class="tile-title">Người dùng</div>
                    <div class="tile-desc">Quản lý tài khoản và phân quyền</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Đơn vị hành chính --}}
                <a href="{{ route('front.geo.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1112 6a2.5 2.5 0 010 5.5z" />
                        </svg>
                    </div>
                    <div class="tile-title">Đơn vị hành chính</div>
                    <div class="tile-desc">Quản lý địa giới hành chính</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Danh mục trường --}}
                <a href="{{ route('front.schools.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4 10h16v2H4zM6 7h12V5H6v2zm0 10h12v-2H6v2z" />
                        </svg>
                    </div>
                    <div class="tile-title">Danh mục trường</div>
                    <div class="tile-desc">Quản lý thông tin trường học</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Dân tộc --}}
                <a href="{{ route('front.ethnicities.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zM3 20a9 9 0 0118 0H3z" />
                        </svg>
                    </div>
                    <div class="tile-title">Dân tộc</div>
                    <div class="tile-desc">Quản lý danh mục dân tộc</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Tôn giáo --}}
                <a href="{{ route('front.religions.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l7 7-7 7-7-7 7-7z" />
                        </svg>
                    </div>
                    <div class="tile-title">Tôn giáo</div>
                    <div class="tile-desc">Quản lý danh mục tôn giáo</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Văn bản --}}
                <a href="{{ route('front.documents.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H7a3 3 0 0 0-3 3v14a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V9z"/>
                            <path d="M14 2v7h7"/>
                            <path d="M9 13h8"/>
                            <path d="M9 17h8"/>
                        </svg>
                    </div>
                    <div class="tile-title">Văn bản</div>
                    <div class="tile-desc">Quản lý tài liệu và văn bản</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>
            </div>
        </div>

        {{-- CỤM ĐIỀU TRA --}}
        <div class="mb-8">
            <h3 class="text-base font-medium text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 6h16v2H4zM4 11h16v2H4zM4 16h10v2H4z" />
                </svg>
                Điều tra
            </h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Danh sách phiếu điều tra --}}
                <a href="{{ route('front.surveyforms.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h10v2H4z" />
                        </svg>
                    </div>
                    <div class="tile-title">Danh sách phiếu điều tra</div>
                    <div class="tile-desc">Quản lý các phiếu điều tra</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Danh sách đối tượng điều tra --}}
                <a href="{{ route('front.surveys.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zM3 20a9 9 0 0118 0H3z" />
                        </svg>
                    </div>
                    <div class="tile-title">Danh sách đối tượng điều tra</div>
                    <div class="tile-desc">Quản lý đối tượng khảo sát</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Tạo khảo sát --}}
                <a href="{{ route('front.surveys.create') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11h-4v4h-2v-4H7v-2h4V7h2v4h4v2z" />
                        </svg>
                    </div>
                    <div class="tile-title">Tạo khảo sát</div>
                    <div class="tile-desc">Tạo mới phiếu khảo sát</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Danh sách xóa tạm --}}
                <a href="{{ route('front.surveys.deleted') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 4h-3.5l-1-1h-5l-1 1H5v2h14V4zM6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12z" />
                        </svg>
                    </div>
                    <div class="tile-title">Danh sách xóa tạm</div>
                    <div class="tile-desc">Khôi phục dữ liệu đã xóa</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Học sinh mầm non --}}
                <a href="{{ route('front.preschool-students.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z" />
                        </svg>
                    </div>
                    <div class="tile-title">Học sinh mầm non</div>
                    <div class="tile-desc">Quản lý học sinh mầm non</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Học sinh trái tuyến --}}
                <a href="{{ route('front.students-off-track.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                        </svg>
                    </div>
                    <div class="tile-title">Học sinh trái tuyến</div>
                    <div class="tile-desc">Quản lý học sinh trái tuyến</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>
            </div>
        </div>

        {{-- CỤM THỐNG KÊ & BÁO CÁO --}}
        <div class="mb-8">
            <h3 class="text-base font-medium text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5 text-purple-600" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 13h4v8H3zM10 3h4v18h-4zM17 8h4v13h-4z" />
                </svg>
                Thống kê & Báo cáo
            </h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Tra cứu thống kê --}}
                <a href="{{ route('front.reports.search.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                        </svg>
                    </div>
                    <div class="tile-title">Tra cứu thống kê</div>
                    <div class="tile-desc">Tìm kiếm và tra cứu dữ liệu</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Quản lý báo cáo --}}
                <a href="{{ route('front.reports.search.management.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" />
                        </svg>
                    </div>
                    <div class="tile-title">Quản lý báo cáo</div>
                    <div class="tile-desc">Quản lý và tạo báo cáo</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Báo cáo kết quả PCGD --}}
                <a href="{{ route('front.reports.summary') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M6 2h9l5 5v15H6zM14 3v5h5" />
                        </svg>
                    </div>
                    <div class="tile-title">Báo cáo kết quả PCGD</div>
                    <div class="tile-desc">Báo cáo phổ cập giáo dục</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Biên bản kiểm tra --}}
                <a href="{{ route('front.reports.export') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z" />
                            <path d="M14 2v6h6" />
                            <path d="M16 13H8" />
                            <path d="M16 17H8" />
                            <path d="M10 9H8" />
                        </svg>
                    </div>
                    <div class="tile-title">Biên bản kiểm tra</div>
                    <div class="tile-desc">Biên bản kiểm tra PCGD-XMC</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- HS tốt nghiệp năm qua --}}
                <a href="{{ route('front.stats.graduates') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 3l8 5-8 5-8-5 8-5zm0 7.09L17.74 8 12 4.91 6.26 8 12 10.09z" />
                        </svg>
                    </div>
                    <div class="tile-title">HS tốt nghiệp năm qua</div>
                    <div class="tile-desc">Thống kê học sinh tốt nghiệp</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Đối tượng 15-18 tuổi tốt nghiệp THCS --}}
                <a href="{{ route('front.stats.thcs-graduates') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 3l8 5-8 5-8-5 8-5zm0 7.09L17.74 8 12 4.91 6.26 8 12 10.09z" />
                        </svg>
                    </div>
                    <div class="tile-title">Đối tượng 15-18 tuổi tốt nghiệp THCS</div>
                    <div class="tile-desc">Thống kê tốt nghiệp THCS</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>
            </div>
        </div>

        {{-- CỤM CẤU HÌNH --}}
        <div class="mb-8">
            <h3 class="text-base font-medium text-slate-700 dark:text-slate-300 mb-4 flex items-center gap-2">
                <svg class="h-5 w-5 text-orange-600" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                    <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z" />
                </svg>
                Cấu hình hệ thống
            </h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {{-- Phân tách thập phân --}}
                <a href="{{ route('front.decimal-format.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                        </svg>
                    </div>
                    <div class="tile-title">Phân tách thập phân</div>
                    <div class="tile-desc">Cấu hình định dạng số</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Cấu hình báo cáo --}}
                <a href="{{ route('front.report-config.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z" />
                        </svg>
                    </div>
                    <div class="tile-title">Cấu hình báo cáo</div>
                    <div class="tile-desc">Thiết lập mẫu báo cáo</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>

                {{-- Công thức thống kê --}}
                <a href="{{ route('front.statistical-formula.index') }}" class="tile">
                    <div class="tile-hero">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2.5 2.5L15 15l3.5-3.5L15 8l-3.5 3.5L8 8l-3.5 3.5L8 15l-3.5 3.5L8 22l3.5-3.5L15 22l3.5-3.5z" />
                        </svg>
                    </div>
                    <div class="tile-title">Công thức thống kê</div>
                    <div class="tile-desc">Quản lý công thức tính toán</div>
                    <svg class="tile-chevron h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M9 6l6 6-6 6" />
                    </svg>
                </a>
            </div>
        </div>
    </section>

    {{-- Tin nhanh / CTA --}}
    <section class="mx-auto max-w-7xl px-4 pb-16 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <h3 class="text-lg font-semibold">Tin nhanh</h3>
            <ul class="mt-3 space-y-2 text-sm">
                <li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Ra mắt
                    giao diện địa chính mới.</li>
                <li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-blue-500"></span> Nâng cấp
                    hệ thống báo cáo.</li>
            </ul>
        </div>
        <div class="card">
            <h3 class="text-lg font-semibold">Bắt đầu nhanh</h3>
            <p class="mt-2 text-sm text-slate-600">Bạn là quản trị viên? Đăng nhập để cấu hình hệ thống.</p>
            @auth
                @if (auth()->user()->hasAdminAccess())
                    <span class="mt-4 inline-flex w-full justify-center text-sm text-slate-500">Bạn đã đăng nhập</span>
                @else
                    <span class="mt-4 inline-flex w-full justify-center text-sm text-slate-500">Bạn đã đăng nhập</span>
                @endif
            @else
                <a href="{{ route('admin.login') }}" class="mt-4 inline-flex w-full justify-center btn-primary">Đăng nhập quản
                    trị</a>
            @endauth
        </div>
    </section>







    {{-- MODAL PROFILE NGƯỜI DÙNG --}}
    <div id="userProfileModal" class="fixed inset-0 z-[60] hidden">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity z-[60]"></div>

        <div class="fixed inset-0 z-[70] overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div
                    class="relative transform overflow-hidden rounded-3xl border bg-white shadow-xl ring-1 ring-black/5 transition-all sm:my-8 sm:w-full sm:max-w-2xl dark:bg-slate-900 dark:border-slate-800">
                    <div class="bg-white px-6 pt-6 pb-4 sm:p-8 dark:bg-slate-900">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-xl font-semibold leading-6 text-slate-900 dark:text-slate-100">Thông tin cá
                                    nhân</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-slate-600 dark:text-slate-400">Xem và cập nhật thông tin cá nhân
                                        của bạn</p>
                                </div>
                            </div>
                        </div>

                        <form id="userProfileForm" method="POST" action="{{ route('profile.update') }}"
                            class="mt-6 space-y-6">
                            @csrf
                            @method('PATCH')

                            {{-- Thông tin cơ bản --}}
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="profile_name"
                                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Họ và
                                            tên <span class="text-red-500">*</span></label>
                                        <input type="text" id="profile_name" name="name"
                                            value="{{ auth()->user()->name }}" required
                                            class="block w-full rounded-xl border-slate-300 bg-white shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 sm:text-sm"
                                            placeholder="Nhập họ và tên">
                                    </div>

                                    <div>
                                        <label for="profile_phone"
                                            class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Số
                                            điện thoại</label>
                                        <input type="tel" id="profile_phone" name="phone_number"
                                            value="{{ auth()->user()->phone_number }}"
                                            class="block w-full rounded-xl border-slate-300 bg-white shadow-sm focus:border-blue-600 focus:ring-blue-600 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-100 sm:text-sm"
                                            placeholder="Nhập số điện thoại">
                                    </div>
                                </div>

                                <div>
                                    <label for="profile_email"
                                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Email</label>
                                    <input type="email" id="profile_email" value="{{ auth()->user()->email }}"
                                        disabled
                                        class="block w-full rounded-xl border-slate-300 bg-slate-50 shadow-sm dark:bg-slate-700 dark:border-slate-600 dark:text-slate-300 sm:text-sm cursor-not-allowed">
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Email được liên kết với tài
                                        khoản Google và không thể thay đổi</p>
                                </div>
                            </div>

                            {{-- Thông tin quyền --}}
                            <div class="border-t border-slate-200 pt-6 dark:border-slate-700">
                                <h4 class="text-sm font-medium text-slate-900 dark:text-slate-100 mb-4">Thông tin quyền
                                </h4>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24"
                                                    fill="currentColor">
                                                    <path
                                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Vai trò
                                                </p>
                                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                                    <span
                                                        class="badge {{ auth()->user()->role === 'admin' ? 'badge-primary' : 'badge-soft' }}">
                                                        {{ auth()->user()->getRoleDisplayName() }}
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24"
                                                    fill="currentColor">
                                                    <path
                                                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Ngày tham
                                                    gia</p>
                                                <p class="text-sm text-slate-500 dark:text-slate-400">
                                                    {{ auth()->user()->created_at->format('d/m/Y') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Phân quyền vai trò --}}
                            @if (auth()->user()->userRole)
                                <div class="border-t border-slate-200 pt-6 dark:border-slate-700">
                                    <h4 class="text-sm font-medium text-slate-900 dark:text-slate-100 mb-4">Phân quyền vai trò</h4>
                                    <div class="space-y-3">
                                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                                        {{ auth()->user()->userRole->display_name ?? auth()->user()->userRole->name }}</p>
                                                    <p class="text-xs text-blue-700 dark:text-blue-300">
                                                        Level: {{ auth()->user()->userRole->level ?? 'N/A' }}</p>
                                                </div>
                                                <div class="flex-shrink-0">
                                                        <span class="badge badge-soft text-xs">{{ auth()->user()->userRole->level ?? 'N/A' }}</span>
                                                    </div>
                                                </div>
                                                @if (auth()->user()->userRole->description)
                                                    <p class="mt-2 text-xs text-blue-600 dark:text-blue-400 italic">
                                                        {{ auth()->user()->userRole->description }}</p>
                                                @endif
                                            </div>
                                    </div>
                                </div>
                            @endif
                        </form>
                    </div>

                    <div
                        class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse sm:space-x-2 sm:space-x-reverse sm:px-8 dark:bg-slate-800">
                        <button type="submit" form="userProfileForm"
                            class="px-4 flex-1 h-9 inline-flex items-center justify-center rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                </path>
                            </svg>
                            Cập nhật thông tin
                        </button>

                        <button type="button" onclick="closeUserProfileModal()"
                            class="px-4 flex-1 h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">
                            Đóng
                        </button>

                        <form method="POST" action="{{ route('logout') }}" class="flex-1">
                          @csrf
                          <button type="submit"
                              class="px-4 w-full h-9 inline-flex items-center justify-center rounded-lg border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700">
                              Đăng xuất
                          </button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>



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
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                    'content'),
                                'Accept': 'application/json',
                            },
                            body: formData,
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('Cập nhật thông tin cá nhân thành công!', 'success');
                                closeUserProfileModal();
                                // Cập nhật tên hiển thị trên trang
                                const nameElements = document.querySelectorAll('.user-name-display');
                                nameElements.forEach(el => {
                                    if (el.textContent.includes('Xin chào')) {
                                        el.textContent = `Xin chào, ${data.user.name}!`;
                                    }
                                });
                            } else {
                                showNotification('Lỗi: ' + (data.message || 'Có lỗi xảy ra khi cập nhật'),
                                    'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error updating profile:', error);
                            showNotification('Có lỗi xảy ra khi cập nhật thông tin cá nhân', 'error');
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



        // Đóng modal khi click bên ngoài
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('fixed') && event.target.classList.contains('inset-0')) {
                event.target.parentElement.classList.add('hidden');
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

            // Icon theo loại thông báo với animation
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

            // Hiển thị thông báo với animation mượt mà
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
                notification.classList.add('translate-x-0');
            }, 100);

            // Tự động ẩn sau 6 giây với animation mượt mà
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
@endsection
