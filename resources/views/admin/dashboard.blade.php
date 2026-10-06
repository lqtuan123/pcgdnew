@extends('layouts.admin')

@section('title', 'Bảng điều khiển')
@section('crumb', 'Bảng điều khiển')

@section('content')
  <!-- Quick actions -->
  <div class="mb-6 flex flex-wrap items-center gap-3">
    {{-- <a href="{{ route('admin.users.create') }}" class="btn-primary">+ Thêm người dùng</a> --}}
    <a href="{{ route('admin.users.index') }}" class="btn-primary">Quản lý người dùng</a>
    <a href="{{ route('admin.schools.index') }}" class="btn-soft">Quản lý trường học</a>
  </div>

  <!-- KPI cards -->
  <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-5">
    <article class="kpi">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Tổng hộ gia đình</div>
          <div class="mt-1 text-2xl font-semibold">{{ number_format($stats['total_households']) }}</div>
        </div>
        <div class="h-10 w-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
        </div>
      </div>
      <div class="mt-3 text-xs text-emerald-600">Hộ gia đình đã khảo sát</div>
    </article>

    <article class="kpi">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Tổng số xã/phường</div>
          <div class="mt-1 text-2xl font-semibold">{{ number_format($stats['total_wards']) }}</div>
        </div>
        <div class="h-10 w-10 rounded-xl bg-green-50 text-green-600 flex items-center justify-center">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        </div>
      </div>
      <div class="mt-3 text-xs text-emerald-600">Đơn vị hành chính cấp xã</div>
    </article>

    <article class="kpi">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Tổng số người</div>
          <div class="mt-1 text-2xl font-semibold">{{ number_format($stats['total_people']) }}</div>
        </div>
        <div class="h-10 w-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zm7 8H5a5 5 0 019-3h-4a5 5 0 00-5 5"/></svg>
        </div>
      </div>
      <div class="mt-3 text-xs text-emerald-600">Người dân trong hệ thống</div>
    </article>

    <article class="kpi">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Tổng số trường</div>
          <div class="mt-1 text-2xl font-semibold">{{ number_format($stats['total_schools']) }}</div>
        </div>
        <div class="h-10 w-10 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg>
        </div>
      </div>
      <div class="mt-3 text-xs text-emerald-600">Trường học đang hoạt động</div>
    </article>

    <article class="kpi">
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-slate-500">Năm học mới nhất</div>
          <div class="mt-1 text-2xl font-semibold">{{ $stats['latest_academic_year'] }}</div>
        </div>
        <div class="h-10 w-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
        </div>
      </div>
      <div class="mt-3 text-xs text-emerald-600">Dữ liệu khảo sát mới nhất</div>
    </article>
  </section>

  <!-- User Statistics -->
  <section class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- User Distribution Chart -->
    <article class="card lg:col-span-2">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Phân bổ người dùng theo cấp</h2>
        <div class="text-sm text-slate-500">Tổng: {{ number_format($userStats['total_users']) }} người dùng</div>
      </div>
      <div class="mt-4">
        <!-- User distribution visualization -->
        <div class="space-y-4">
          <div class="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
            <div class="flex items-center gap-3">
              <div class="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-800 flex items-center justify-center">
                <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M12 12a4 4 0 100-8 4 4 0 000 8zm7 8H5a5 5 0 019-3h-4a5 5 0 00-5 5"/>
                </svg>
              </div>
              <div>
                <div class="font-medium text-slate-900 dark:text-slate-100">Tổng người dùng</div>
                <div class="text-sm text-slate-500 dark:text-slate-400">Tất cả người dùng trong hệ thống</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($userStats['total_users']) }}</div>
              <div class="text-sm text-slate-500">100%</div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-xl">
              <div class="flex items-center gap-3 mb-2">
                <div class="h-6 w-6 rounded-full bg-green-100 dark:bg-green-800 flex items-center justify-center">
                  <svg class="h-3 w-3 text-green-600 dark:text-green-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                  </svg>
                </div>
                <span class="font-medium text-slate-900 dark:text-slate-100">Cấp Tỉnh</span>
              </div>
              <div class="text-xl font-bold text-green-600 dark:text-green-400">{{ number_format($userStats['province_users']) }}</div>
              <div class="text-sm text-slate-500">{{ $userStats['total_users'] > 0 ? round(($userStats['province_users'] / $userStats['total_users']) * 100, 1) : 0 }}%</div>
            </div>

            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
              <div class="flex items-center gap-3 mb-2">
                <div class="h-6 w-6 rounded-full bg-blue-100 dark:bg-blue-800 flex items-center justify-center">
                  <svg class="h-3 w-3 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                  </svg>
                </div>
                <span class="font-medium text-slate-900 dark:text-slate-100">Cấp Xã/Phường</span>
              </div>
              <div class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($userStats['ward_users']) }}</div>
              <div class="text-sm text-slate-500">{{ $userStats['total_users'] > 0 ? round(($userStats['ward_users'] / $userStats['total_users']) * 100, 1) : 0 }}%</div>
            </div>

            <div class="p-4 bg-orange-50 dark:bg-orange-900/20 rounded-xl">
              <div class="flex items-center gap-3 mb-2">
                <div class="h-6 w-6 rounded-full bg-orange-100 dark:bg-orange-800 flex items-center justify-center">
                  <svg class="h-3 w-3 text-orange-600 dark:text-orange-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
                  </svg>
                </div>
                <span class="font-medium text-slate-900 dark:text-slate-100">Cấp Trường</span>
              </div>
              <div class="text-xl font-bold text-orange-600 dark:text-orange-400">{{ number_format($userStats['school_users']) }}</div>
              <div class="text-sm text-slate-500">{{ $userStats['total_users'] > 0 ? round(($userStats['school_users'] / $userStats['total_users']) * 100, 1) : 0 }}%</div>
            </div>

            <div class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-xl">
              <div class="flex items-center gap-3 mb-2">
                <div class="h-6 w-6 rounded-full bg-purple-100 dark:bg-purple-800 flex items-center justify-center">
                  <svg class="h-3 w-3 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                  </svg>
                </div>
                <span class="font-medium text-slate-900 dark:text-slate-100">Quản trị viên</span>
              </div>
              <div class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($userStats['admin_users']) }}</div>
              <div class="text-sm text-slate-500">{{ $userStats['total_users'] > 0 ? round(($userStats['admin_users'] / $userStats['total_users']) * 100, 1) : 0 }}%</div>
            </div>
          </div>
        </div>
      </div>
    </article>

    <!-- System Overview -->
    <article class="card">
      <h2 class="text-lg font-semibold">Tổng quan hệ thống</h2>
      <div class="mt-4 space-y-4">
        <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-medium text-slate-900 dark:text-slate-100">Dữ liệu khảo sát</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">Năm học hiện tại</div>
            </div>
            <div class="text-right">
              <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ $stats['latest_academic_year'] }}</div>
            </div>
          </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-medium text-slate-900 dark:text-slate-100">Phạm vi địa lý</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">Xã/phường có dữ liệu</div>
            </div>
            <div class="text-right">
              <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ number_format($stats['total_wards']) }}</div>
            </div>
          </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-medium text-slate-900 dark:text-slate-100">Hệ thống giáo dục</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">Trường đang hoạt động</div>
            </div>
            <div class="text-right">
              <div class="text-lg font-bold text-orange-600 dark:text-orange-400">{{ number_format($stats['total_schools']) }}</div>
            </div>
          </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-xl">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-sm font-medium text-slate-900 dark:text-slate-100">Quy mô dân số</div>
              <div class="text-xs text-slate-500 dark:text-slate-400">Người trong hệ thống</div>
            </div>
            <div class="text-right">
              <div class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ number_format($stats['total_people']) }}</div>
            </div>
          </div>
        </div>
      </div>
    </article>
  </section>

  <!-- Recent users -->
  <section class="mt-8 card overflow-hidden">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Người dùng gần đây</h2>
      <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-600 hover:underline">Xem tất cả</a>
    </div>

    <div class="-mx-4 mt-4 overflow-x-auto">
      <table class="min-w-full divide-y divide-slate-200">
        <thead class="bg-slate-50">
          <tr>
            <th class="th">Tên</th>
            <th class="th">Email</th>
            <th class="th">Số điện thoại</th>
            <th class="th">Ngày tạo</th>
            <th class="th text-right">Quyền</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @foreach($recentUsers as $user)
          <tr class="hover:bg-slate-50">
            <td class="td">{{ $user->name }}</td>
            <td class="td">{{ $user->email }}</td>
            <td class="td">{{ $user->phone_number ?? 'Chưa cập nhật' }}</td>
            <td class="td">{{ $user->created_at->format('d/m/Y') }}</td>
            <td class="td text-right">
              @if($user->role_level)
                <span class="badge {{ $user->role_level === 'province' ? 'badge-success' : ($user->role_level === 'ward' ? 'badge-info' : 'badge-warning') }}">
                  @switch($user->role_level)
                    @case('province')
                      Tỉnh/Thành phố
                      @break
                    @case('ward')
                      Xã/Phường
                      @break
                    @case('school')
                      Trường học
                      @break
                    @default
                      Chưa phân quyền
                  @endswitch
                </span>
              @else
                <span class="badge badge-soft">Chưa phân quyền</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>
@endsection
