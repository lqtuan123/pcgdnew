@extends('layouts.front')
@section('title', 'Quản lý Báo cáo')
@section('description', 'Quản lý và theo dõi tiến độ các báo cáo giáo dục')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Quản lý Báo cáo</h1>
                <p class="text-gray-600 mt-2">Quản lý và theo dõi tiến độ các báo cáo giáo dục</p>
            </div>
        </div>
    </div>


    

    

    {{-- School Deadlines Display --}}
    @if(auth()->user() && auth()->user()->role_level === 'school' && isset($schoolDeadlines))
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Danh sách các báo cáo Xã gửi xuống trường</h3>
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-gray-700">Năm học:</label>
                <select id="schoolDeadlineYearFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $selectedYear = request('school_deadline_year') ?? $currentYear . '-' . ($currentYear + 1);
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $selectedYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
                </div>
                </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhóm báo cáo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tên báo cáo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Năm học</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hạn nộp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ghi chú</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($schoolDeadlines as $deadline)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $deadline->group->name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $deadline->detail->name ?? 'Tất cả' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $deadline->academic_year ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ \Carbon\Carbon::parse($deadline->deadline)->isPast() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ \Carbon\Carbon::parse($deadline->deadline)->format('d/m/Y') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $deadline->note ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                            <div class="flex flex-col items-center justify-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                                <p>Chưa có hạn nộp nào từ Xã</p>
                </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
                </div>
            </div>
    @endif

    {{-- Upload (School) --}}
    @if(auth()->user() && auth()->user()->role_level === 'school')
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Tải báo cáo lên (Cấp trường)</h3>
            <div class="inline-flex rounded-md shadow-sm overflow-hidden" role="group">
                <button type="button" onclick="toggleUploadMode('single')" id="singleModeBtn" class="px-3 py-1 text-sm bg-blue-600 text-white">Đơn lẻ</button>
                <button type="button" onclick="toggleUploadMode('multi')" id="multiModeBtn" class="px-3 py-1 text-sm bg-gray-100 text-gray-700">Nhiều form</button>
            </div>
        </div>
        <form id="singleUploadForm" action="{{ route('front.reports.search.management.submissions.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-12 gap-4">
            @csrf
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nhóm báo cáo</label>
                <select id="report_group_id" name="report_group_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    <option value="">Chọn nhóm</option>
                    @foreach(($groups ?? []) as $g)
                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Loại báo cáo</label>
                <select id="report_detail_id" name="report_detail_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    <option value="">Chọn loại báo cáo</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Năm học</label>
                <select name="academic_year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Chọn năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $currentAcademicYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
            </div>
            
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">File báo cáo</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.zip" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required />
            </div>
            <div class="md:col-span-12 flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">Tải lên</button>
            </div>
        </form>

        {{-- Multi-form Upload --}}
        <div id="multiUploadBlock" class="mt-6 hidden">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-medium text-gray-900">Tạo nhiều báo cáo (nhiều form)</h4>
                <div class="text-xs text-gray-500">Tối đa 10 mục/lần</div>
            </div>
            <form id="multiForm" action="{{ route('front.reports.search.management.submissions.multi-store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div id="multiFormContainer" class="space-y-4"></div>
                <div class="flex items-center justify-between">
                    <button type="button" id="addMultiItemBtn" class="px-3 py-1 bg-slate-600 text-white rounded text-sm">Thêm mục</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded">Tải lên</button>
                </div>
            </form>
        </div>

        
    </div>
    @endif

    {{-- Deadline (Province -> Ward) compact UI --}}
    @if(auth()->user() && auth()->user()->role_level === 'province' && isset($wards))
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Quản lý hạn nộp (Xã/Phường)</h3>
            <div class="inline-flex rounded-md shadow-sm" role="group">
                <button type="button" onclick="switchDeadlineTab('ward-form')" id="btn-ward-form" class="px-3 py-1 text-sm bg-blue-600 text-white rounded-l">Cài đặt</button>
                <button type="button" onclick="switchDeadlineTab('ward-list')" id="btn-ward-list" class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-r">Danh sách</button>
            </div>
        </div>
        <div id="ward-form" class="block">
        <form action="{{ route('front.reports.search.management.deadlines.ward') }}" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Xã/Phường (chọn nhiều)</label>
                <select name="target_ward_ids[]" class="w-full px-3 py-2 border rounded-lg" multiple size="5">
                    @foreach($wards as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                <label class="inline-flex items-center mt-2 text-xs text-gray-600"><input type="checkbox" name="apply_all" value="1" class="mr-2">Áp dụng tất cả Xã/Phường</label>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nhóm báo cáo</label>
                <select name="report_group_id" id="deadline_report_group_id" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="">Chọn nhóm</option>
                    @foreach(($groups ?? []) as $g)
                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Loại báo cáo (tuỳ chọn)</label>
                <select name="report_detail_id" id="deadline_report_detail_id" class="w-full px-3 py-2 border rounded-lg">
                    <option value="">Tất cả loại</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Năm học</label>
                <select name="academic_year" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="">Chọn năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $currentAcademicYear = $currentYear . '-' . $endYear;
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $currentAcademicYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Hạn nộp</label>
                <input type="date" name="deadline" min="{{ date('Y-m-d', strtotime('+1 day')) }}" class="w-full px-3 py-2 border rounded-lg" required>
                <p class="text-[11px] text-gray-500 mt-1">Áp dụng cho các Ward đã chọn</p>
            </div>
            <div class="flex items-end">
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">Lưu hạn nộp</button>
            </div>
        </form>
        </div>
        <div id="ward-list" class="hidden">
            {{-- Filter Section --}}
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Bộ lọc</h4>
                <form method="GET" action="{{ route('front.reports.search.management.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Năm học</label>
                        <select name="filter_academic_year" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">Tất cả năm học</option>
                            @php
                                $currentYear = date('Y');
                                $endYear = $currentYear + 1;
                                $selectedYear = request('filter_academic_year');
                                for ($year = $endYear; $year >= 2000; $year--) {
                                    $academicYear = $year . '-' . ($year + 1);
                                    $selected = ($academicYear === $selectedYear) ? 'selected' : '';
                                    echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                                }
                            @endphp
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Xã/Phường</label>
                        <select name="filter_ward_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">Tất cả xã/phường</option>
                            @foreach($wards as $ward)
                                <option value="{{ $ward->id }}" {{ request('filter_ward_id') == $ward->id ? 'selected' : '' }}>
                                    {{ $ward->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                            Lọc
                        </button>
                        <a href="{{ route('front.reports.search.management.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg text-sm hover:bg-gray-600">
                            Xóa lọc
                        </a>
                    </div>
                </form>
            </div>
            
            @if(isset($deadlines) && $deadlines->count() > 0)
            <div class="mt-4">
                {{-- Bulk Actions --}}
                <div class="bg-blue-50 rounded-lg p-4 mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Thao tác hàng loạt</h4>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="selectAllDeadlines" onclick="toggleAllDeadlines()" class="rounded">
                            <label for="selectAllDeadlines" class="text-sm text-gray-600">Chọn tất cả</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" onclick="handleBulkAction('activate', 'Bật các hạn nộp đã chọn?')" class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                                Bật
                            </button>
                            <button type="button" onclick="handleBulkAction('deactivate', 'Tắt các hạn nộp đã chọn?')" class="px-3 py-1 bg-amber-600 text-white rounded text-sm hover:bg-amber-700">
                                Tắt
                            </button>
                            <button type="button" onclick="handleBulkAction('delete', 'Xóa các hạn nộp đã chọn? Hành động không thể hoàn tác!')" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                                Xóa
                            </button>
                        </div>
                    </div>
                </div>
                <form id="bulkDeadlineForm" method="POST" action="{{ route('front.reports.search.management.deadlines.bulk') }}">
                    @csrf
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3"><input type="checkbox" id="selectAllDeadlines" onclick="toggleAllDeadlines()"></th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Mục tiêu</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Báo cáo</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Năm học</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Hạn nộp</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Ghi chú</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($deadlines as $dl)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4"><input type="checkbox" name="selected_ids[]" value="{{ $dl->id }}" class="deadline-checkbox"></td>
                                <td class="px-6 py-4 text-sm">{{ $dl->ward->name ?? ('Xã/Phường #'.$dl->target_ward_id) }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="text-sm text-gray-900">{{ optional($groups->firstWhere('id', $dl->report_group_id))->name ?? '—' }}</div>
                                    @if($dl->report_detail_id)
                                        <div class="text-[11px] text-gray-500">ID loại: {{ $dl->report_detail_id }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm">{{ $dl->academic_year ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <form method="POST" action="{{ route('front.reports.search.management.deadlines.update', $dl) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="date" name="deadline" value="{{ optional($dl->deadline)->format('Y-m-d') }}" min="{{ date('Y-m-d', strtotime('+1 day')) }}" class="px-2 py-1 border rounded text-sm">
                                        <input type="text" name="note" value="{{ $dl->note }}" placeholder="Ghi chú" class="px-2 py-1 border rounded w-44 text-sm" title="{{ $dl->note }}">
                                        <button class="px-3 py-1 bg-blue-600 text-white rounded text-xs" onclick="setTimeout(()=>window.location.reload(),50)">Lưu</button>
                                    </form>
                                </td>
                                <td class="px-6 py-4 text-sm truncate max-w-[200px]" title="{{ $dl->note }}">{{ $dl->note ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm">@if($dl->is_active)<span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded">Đang hiệu lực</span>@else<span class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded">Đã tắt</span>@endif</td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        @if($dl->is_active)
                                        <form method="POST" action="{{ route('front.reports.search.management.deadlines.deactivate', $dl) }}" onsubmit="return confirm('Tắt hạn nộp này?')">
                                            @csrf
                                            <button class="px-3 py-1 bg-amber-600 text-white rounded text-xs">Tắt</button>
                                        </form>
                                        @else
                                        <form method="POST" action="{{ route('front.reports.search.management.deadlines.activate', $dl) }}" onsubmit="return confirm('Bật hạn nộp này?')">
                                            @csrf
                                            <button class="px-3 py-1 bg-green-600 text-white rounded text-xs">Bật</button>
                                        </form>
                                        @endif
                                        <form method="POST" action="{{ route('front.reports.search.management.deadlines.destroy', $dl) }}" onsubmit="return confirm('Xóa hạn nộp này? Hành động không thể hoàn tác!')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="px-3 py-1 bg-red-600 text-white rounded text-xs">Xóa</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $deadlines->appends(request()->query())->links() }}</div>
                </form>
            </div>
            @else
            <div class="mt-4 text-center py-8">
                <div class="text-gray-500">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Không có hạn nộp</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if(request()->hasAny(['filter_academic_year', 'filter_ward_id']))
                            Không tìm thấy hạn nộp phù hợp với bộ lọc đã chọn.
                        @else
                            Chưa có hạn nộp nào được thiết lập.
            @endif
                    </p>
                    @if(request()->hasAny(['filter_academic_year', 'filter_ward_id']))
                        <div class="mt-4">
                            <a href="{{ route('front.reports.search.management.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                Xóa bộ lọc
                            </a>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Ward Deadlines Display --}}
    @if(auth()->user() && auth()->user()->role_level === 'ward' && isset($wardDeadlines))
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Hạn nộp các báo cáo từ Tỉnh gửi xuống</h3>
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-gray-700">Năm học:</label>
                <select id="wardDeadlineYearFilter" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $selectedYear = request('ward_deadline_year') ?? $currentYear . '-' . ($currentYear + 1);
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $selectedYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhóm báo cáo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tên báo cáo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hạn nộp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ghi chú</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($wardDeadlines as $deadline)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $deadline->group->name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $deadline->detail->name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ \Carbon\Carbon::parse($deadline->deadline)->isPast() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ \Carbon\Carbon::parse($deadline->deadline)->format('d/m/Y') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            {{ $deadline->note ?? '-' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                            <div class="flex flex-col items-center justify-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p>Chưa có hạn nộp nào từ Tỉnh</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Deadline (Ward -> School) compact UI --}}
    @if(auth()->user() && auth()->user()->role_level === 'ward' && isset($schools))
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Quản lý hạn nộp cho cấp Trường</h3>
            <div class="inline-flex rounded-md shadow-sm" role="group">
                <button type="button" onclick="switchSchoolDeadlineTab('school-form')" id="btn-school-form" class="px-3 py-1 text-sm bg-blue-600 text-white rounded-l">Cài đặt</button>
                <button type="button" onclick="switchSchoolDeadlineTab('school-list')" id="btn-school-list" class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-r">Danh sách</button>
            </div>
        </div>
        <div id="school-form" class="block">
        <form action="{{ route('front.reports.search.management.deadlines.school') }}" method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Trường (chọn nhiều)</label>
                <select name="target_school_ids[]" class="w-full px-3 py-2 border rounded-lg" multiple size="5">
                    @foreach($schools as $sc)
                        <option value="{{ $sc->id }}">{{ $sc->name }}</option>
                    @endforeach
                </select>
                <label class="inline-flex items-center mt-2 text-xs text-gray-600"><input type="checkbox" name="apply_all" value="1" class="mr-2">Áp dụng tất cả Trường</label>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nhóm báo cáo</label>
                <select name="report_group_id" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="">Chọn nhóm</option>
                    @foreach(($groups ?? []) as $g)
                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Loại báo cáo (tuỳ chọn)</label>
                <select name="school_report_detail_id" id="school_report_detail_id" class="w-full px-3 py-2 border rounded-lg">
                    <option value="">Tất cả loại</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Năm học</label>
                <select name="school_academic_year" class="w-full px-3 py-2 border rounded-lg" required>
                    <option value="">Chọn năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $selectedYear = request('school_academic_year');
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $selectedYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Hạn nộp</label>
                <input type="date" name="school_deadline" min="{{ date('Y-m-d', strtotime('+1 day')) }}" class="w-full px-3 py-2 border rounded-lg" required>
                <p class="text-[11px] text-gray-500 mt-1">Áp dụng cho các Trường đã chọn</p>
            </div>
            <div class="flex items-end">
                <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">Lưu hạn nộp</button>
            </div>
        </form>
        </div>
        <div id="school-list" class="hidden">
            {{-- School Deadlines Filter --}}
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Bộ lọc hạn nộp</h4>
                <form method="GET" action="{{ route('front.reports.search.management.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Năm học</label>
                        <select name="school_filter_academic_year" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">Tất cả năm học</option>
                            @php
                                $currentYear = date('Y');
                                $endYear = $currentYear + 1;
                                $selectedYear = request('school_filter_academic_year');
                                for ($year = $endYear; $year >= 2000; $year--) {
                                    $academicYear = $year . '-' . ($year + 1);
                                    $selected = ($academicYear === $selectedYear) ? 'selected' : '';
                                    echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                                }
                            @endphp
                        </select>
    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Trường</label>
                        <select name="school_filter_school_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">Tất cả trường</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}" {{ request('school_filter_school_id') == $school->id ? 'selected' : '' }}>{{ $school->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                            Lọc
                        </button>
                        <a href="{{ route('front.reports.search.management.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg text-sm hover:bg-gray-600">
                            Xóa lọc
                        </a>
                    </div>
                </form>
            </div>

            {{-- School Deadlines Bulk Actions --}}
            <div class="bg-blue-50 rounded-lg p-4 mb-4">
                <h4 class="text-sm font-medium text-gray-700 mb-3">Thao tác hàng loạt</h4>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="selectAllSchoolDeadlines" onclick="toggleAllSchoolDeadlines()" class="rounded">
                        <label for="selectAllSchoolDeadlines" class="text-sm text-gray-600">Chọn tất cả</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="handleSchoolBulkAction('activate', 'Bật các hạn nộp đã chọn?')" class="px-3 py-1 bg-green-600 text-white rounded text-sm hover:bg-green-700">
                            Bật
                        </button>
                        <button type="button" onclick="handleSchoolBulkAction('deactivate', 'Tắt các hạn nộp đã chọn?')" class="px-3 py-1 bg-amber-600 text-white rounded text-sm hover:bg-amber-700">
                            Tắt
                        </button>
                        <button type="button" onclick="handleSchoolBulkAction('delete', 'Xóa các hạn nộp đã chọn? Hành động không thể hoàn tác!')" class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                            Xóa
                        </button>
                    </div>
                </div>
            </div>

            {{-- School Deadlines List --}}
            <form id="bulkSchoolDeadlineForm" method="POST" action="{{ route('front.reports.search.management.deadlines.school.bulk') }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3"><input type="checkbox" id="selectAllSchoolDeadlines" onclick="toggleAllSchoolDeadlines()"></th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Trường</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Nhóm báo cáo</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Loại báo cáo</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Năm học</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Hạn nộp</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-6 py-3 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($schoolDeadlines ?? [] as $dl)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4"><input type="checkbox" name="selected_ids[]" value="{{ $dl->id }}" class="school-deadline-checkbox"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $dl->school->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $dl->group->name ?? 'N/A' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $dl->detail->name ?? 'Tất cả' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $dl->academic_year ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ \Carbon\Carbon::parse($dl->deadline)->isPast() ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                        {{ \Carbon\Carbon::parse($dl->deadline)->format('d/m/Y') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $dl->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $dl->is_active ? 'Đang hoạt động' : 'Tạm dừng' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        @if($dl->is_active)
                                            <form method="POST" action="{{ route('front.reports.search.management.deadlines.school.deactivate', $dl) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 bg-amber-600 text-white rounded text-xs hover:bg-amber-700">
                                                    Tắt
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('front.reports.search.management.deadlines.school.activate', $dl) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                                    Bật
                                                </button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('front.reports.search.management.deadlines.school.delete', $dl) }}" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa hạn nộp này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">
                                                Xóa
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                    <div class="flex flex-col items-center justify-center py-8">
                                        <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <p>Chưa có hạn nộp nào</p>
                                        @if(request()->hasAny(['school_filter_academic_year', 'school_filter_school_id']))
                                            <a href="{{ route('front.reports.search.management.index') }}" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded text-sm hover:bg-blue-700">
                                                Xóa bộ lọc
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(isset($schoolDeadlines) && $schoolDeadlines->hasPages())
                <div class="mt-4">{{ $schoolDeadlines->appends(request()->query())->links() }}</div>
                @endif
            </form>
        </div>
    </div>
    @endif

    {{-- Upload (Ward) --}}
    @if(auth()->user() && auth()->user()->role_level === 'ward')
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Tải báo cáo lên (Cấp xã)</h3>
            <div class="inline-flex rounded-md shadow-sm overflow-hidden" role="group">
                <button type="button" onclick="toggleWardUploadMode('single')" id="wardSingleModeBtn" class="px-3 py-1 text-sm bg-blue-600 text-white">Đơn lẻ</button>
                <button type="button" onclick="toggleWardUploadMode('multi')" id="wardMultiModeBtn" class="px-3 py-1 text-sm bg-gray-100 text-gray-700">Nhiều form</button>
            </div>
        </div>
        <form id="wardSingleUploadForm" action="{{ route('front.reports.search.management.submissions.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-12 gap-4">
            @csrf
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nhóm báo cáo</label>
                <select id="ward_report_group_id" name="report_group_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    <option value="">Chọn nhóm báo cáo</option>
                    @foreach(($groups ?? []) as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Loại báo cáo</label>
                <select id="ward_report_detail_id" name="report_detail_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Chọn loại báo cáo</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Năm học</label>
                <select name="academic_year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    <option value="">Chọn năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $currentAcademicYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">File báo cáo</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
            </div>
            <div class="md:col-span-12">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                    Tải báo cáo lên
                </button>
            </div>
        </form>

        {{-- Multi Upload Form --}}
        <div id="wardMultiUploadForm" class="hidden">
            <div class="mt-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-md font-medium text-gray-900">Tải nhiều báo cáo</h4>
                    <button type="button" onclick="addWardUploadItem()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm">
                        Thêm báo cáo
                    </button>
                </div>
                <form id="wardMultiUploadFormSubmit" action="{{ route('front.reports.search.management.submissions.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div id="wardUploadItems">
                        <!-- Items will be added dynamically -->
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">
                            Tải tất cả báo cáo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Filters --}}
    @if(auth()->user() && auth()->user()->role_level === 'ward')
    <div id="filters-block" class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Bộ lọc báo cáo</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nhóm báo cáo</label>
                <select name="report_group_id" id="filter_report_group_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả nhóm báo cáo</option>
                    @foreach(($groups ?? []) as $group)
                        <option value="{{ $group->id }}" @selected(request('report_group_id') == $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Loại báo cáo</label>
                <select name="report_detail_id" id="filter_report_detail_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả loại báo cáo</option>
                    @if(request('report_group_id'))
                        @php
                            $selectedGroup = $groups->find(request('report_group_id'));
                        @endphp
                        @if($selectedGroup && $selectedGroup->report_details)
                            @foreach($selectedGroup->report_details as $detail)
                                <option value="{{ $detail->id }}" @selected(request('report_detail_id') == $detail->id)>{{ $detail->name }}</option>
                            @endforeach
                        @endif
                    @endif
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Năm học</label>
                <select name="academic_year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
                        $selectedAcademicYear = request('academic_year', $currentAcademicYear);
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $selectedAcademicYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả trạng thái</option>
                    <option value="submitted" @selected(request('status') == 'submitted')>Đã gửi</option>
                    <option value="under_review" @selected(request('status') == 'under_review')>Đang xem xét</option>
                    <option value="returned" @selected(request('status') == 'returned')>Trả lại</option>
                    <option value="approved" @selected(request('status') == 'approved')>Đã duyệt</option>
                    <option value="rejected" @selected(request('status') == 'rejected')>Từ chối</option>
                </select>
            </div>

            <div class="md:col-span-2 lg:col-span-4 flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Lọc
                </button>
                <a href="{{ route('front.reports.search.management.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Làm mới
                </a>
            </div>
        </form>
    </div>
    @endif

    {{-- Ward Own Reports --}}
    @if(auth()->user() && auth()->user()->role_level === 'ward' && isset($wardSentSubmissions))
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Báo cáo đã gửi (Cấp xã)</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Báo cáo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhóm</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Năm học</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày gửi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phản hồi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($wardSentSubmissions as $submission)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <div>
                                <div class="font-medium">{{ $submission->detail->name ?? 'N/A' }}</div>
                                @if($submission->file_path)
                                    <a href="{{ Storage::url($submission->file_path) }}" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 underline">
                                        Tải file
                                    </a>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $submission->group->name ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $submission->academic_year ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $submission->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            @switch($submission->status)
                                @case('submitted')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Đã gửi</span>
                                    @break
                                @case('under_review')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Đang xem xét</span>
                                    @break
                                @case('approved')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Đã duyệt</span>
                                    @break
                                @case('returned')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Trả lại</span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            @if($submission->stage === 'ward' && $submission->feedback)
                                <button onclick="openWardFeedbackModal('{{ route('front.reports.search.management.submissions.view-feedback', $submission) }}')" class="text-blue-600 hover:text-blue-800 underline">
                                    Xem phản hồi
                                </button>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center gap-2">
                                @if($submission->stage === 'ward' && $submission->status === 'submitted')
                                    <button onclick="openWardEditModal({{ $submission->id }}, {{ $submission->report_group_id }}, {{ $submission->report_detail_id }}, '{{ $submission->academic_year }}')" class="px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">
                                        Sửa
                                    </button>
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.forward', $submission) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700" onclick="return confirm('Bạn có chắc chắn muốn chuyển tiếp báo cáo này?')">
                                            Chuyển tiếp
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.destroy', $submission) }}" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa báo cáo này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">
                                            Xóa
                                        </button>
                                    </form>
                                @elseif($submission->stage === 'ward' && $submission->status === 'returned')
                                    <button onclick="openWardEditModal({{ $submission->id }}, {{ $submission->report_group_id }}, {{ $submission->report_detail_id }}, '{{ $submission->academic_year }}')" class="px-2 py-1 bg-blue-600 text-white rounded text-xs hover:bg-blue-700">
                                        Sửa
                                    </button>
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.forward', $submission) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700" onclick="return confirm('Bạn có chắc chắn muốn chuyển tiếp báo cáo này?')">
                                            Chuyển tiếp
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.destroy', $submission) }}" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa báo cáo này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-2 py-1 bg-red-600 text-white rounded text-xs hover:bg-red-700">
                                            Xóa
                                        </button>
                                    </form>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                            <div class="flex flex-col items-center justify-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p>Chưa có báo cáo nào</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($wardSentSubmissions->hasPages())
        <div class="mt-4">{{ $wardSentSubmissions->appends(request()->query())->links() }}</div>
        @endif
    </div>
    @endif

    {{-- Statistics Cards for Ward (School Reports Only) --}}
    @if(auth()->user() && auth()->user()->role_level === 'ward')
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center">
                <div class="p-3 bg-blue-100 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Tổng báo cáo</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $schoolStats['total'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Đã duyệt</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $schoolStats['approved'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Đang xem</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $schoolStats['under_review'] ?? 0 }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="flex items-center">
                <div class="p-3 bg-red-100 rounded-lg">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Trả lại</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $schoolStats['returned'] ?? 0 }}</p>
                </div>
            </div>
        </div>
    </div>
    @endif


    @if(auth()->user() && auth()->user()->role_level === 'ward' && isset($sentSubmissions) && $sentSubmissions && $sentSubmissions->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Báo cáo Cấp trường</h3>
            <p class="text-sm text-gray-500">Hiển thị {{ $sentSubmissions->total() }} báo cáo từ các trường trong địa bàn.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Báo cáo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhóm</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trường</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Đang ở cấp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày gửi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phản hồi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($sentSubmissions as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $s->detail->name ?? '—' }}</div>
                            <div class="text-xs text-gray-500">Năm học: {{ $s->academic_year ?? '—' }}</div>
                            <div class="text-xs">
                                <a target="_blank" href="{{ asset('storage/'.$s->file_path) }}" class="text-blue-600 hover:underline">Tải file</a>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $s->group->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-900">{{ $s->school->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm">
                            @php
                                $stageNames = [
                                    'school' => 'Trường',
                                    'ward' => 'Xã/Phường',
                                    'province' => 'Tỉnh/Thành phố'
                                ];
                            @endphp
                            {{ $stageNames[$s->stage] ?? ucfirst($s->stage) }}
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusColor = [
                                    'submitted' => 'bg-blue-100 text-blue-800',
                                    'under_review' => 'bg-yellow-100 text-yellow-800',
                                    'returned' => 'bg-orange-100 text-orange-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                ][$s->status] ?? 'bg-slate-100 text-slate-800';
                            @endphp
                            @php
                                $statusNames = [
                                    'submitted' => 'Đã gửi',
                                    'under_review' => 'Đang xem xét',
                                    'returned' => 'Trả lại',
                                    'approved' => 'Đã duyệt',
                                    'rejected' => 'Từ chối'
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusColor }}">
                                {{ $statusNames[$s->status] ?? ucfirst($s->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ optional($s->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            @if($s->feedback)
                                <button onclick="showFeedbackModal('{{ addslashes($s->feedback) }}', '{{ optional($s->feedback_at)->format('d/m/Y H:i') }}')" class="text-blue-600 hover:text-blue-900 underline">
                                    Xem phản hồi
                                </button>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex flex-wrap gap-1">
                                @if($s->stage === 'ward')
                                <div class="flex flex-wrap gap-1">
                                    @if($s->status !== 'under_review')
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.update-status', $s) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="under_review">
                                        <input type="hidden" name="feedback" value="">
                                        <button class="px-2 py-1 bg-yellow-600 text-white rounded text-xs" title="Đặt trạng thái đang xem xét">
                                            Đang xem
                                        </button>
                                    </form>
                                    @endif
                                    
                                    @if($s->status !== 'approved')
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.update-status', $s) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <input type="hidden" name="feedback" value="">
                                        <button class="px-2 py-1 bg-green-600 text-white rounded text-xs" title="Duyệt báo cáo">
                                            Duyệt
                                        </button>
                                    </form>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.forward', $s) }}" class="inline">
                                        @csrf
                                        <button class="px-2 py-1 bg-emerald-600 text-white rounded text-xs" title="Chuyển tiếp lên Tỉnh/Thành phố">
                                            Chuyển tiếp
                                        </button>
                                    </form>
                                    
                                    <button onclick="openFeedbackModal('{{ route('front.reports.search.management.submissions.update-status', $s) }}', 'School')" class="px-2 py-1 bg-red-600 text-white rounded text-xs" title="Trả lại cho School">
                                        Trả lại
                                    </button>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination for sent submissions --}}
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            @if(isset($sentSubmissions) && method_exists($sentSubmissions, 'hasPages') && $sentSubmissions->hasPages())
                {{ $sentSubmissions->links() }}
            @endif
        </div>
    </div>
    @endif

    {{-- Filters for School --}}
    @if(auth()->user() && auth()->user()->role_level === 'school')
    <div id="school-filters-block" class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Bộ lọc báo cáo</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nhóm báo cáo</label>
                <select name="school_report_group_id" id="school_filter_report_group_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả nhóm báo cáo</option>
                    @foreach(($groups ?? []) as $group)
                        <option value="{{ $group->id }}" @selected(request('school_report_group_id') == $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Loại báo cáo</label>
                <select name="school_report_detail_id" id="school_filter_report_detail_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả loại báo cáo</option>
                    @if(request('school_report_group_id'))
                        @php
                            $selectedGroup = $groups->find(request('school_report_group_id'));
                        @endphp
                        @if($selectedGroup && $selectedGroup->report_details)
                            @foreach($selectedGroup->report_details as $detail)
                                <option value="{{ $detail->id }}" @selected(request('school_report_detail_id') == $detail->id)>{{ $detail->name }}</option>
                            @endforeach
                        @endif
                    @endif
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Năm học</label>
                <select name="school_academic_year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
                        $selectedAcademicYear = request('school_academic_year', $currentAcademicYear);
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $selectedAcademicYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                <select name="school_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Tất cả trạng thái</option>
                    <option value="submitted" @selected(request('school_status') == 'submitted')>Đã gửi</option>
                    <option value="under_review" @selected(request('school_status') == 'under_review')>Đang xem xét</option>
                    <option value="returned" @selected(request('school_status') == 'returned')>Trả lại</option>
                    <option value="approved" @selected(request('school_status') == 'approved')>Đã duyệt</option>
                    <option value="rejected" @selected(request('school_status') == 'rejected')>Từ chối</option>
                </select>
            </div>

            <div class="md:col-span-2 lg:col-span-4 flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Lọc
                </button>
                <a href="{{ route('front.reports.search.management.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-medium transition-colors">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Làm mới
                </a>
            </div>
        </form>
    </div>
    @endif

    @if(auth()->user() && auth()->user()->role_level === 'school' && isset($sentSubmissions) && $sentSubmissions && $sentSubmissions->count() > 0)
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Danh sách báo cáo</h3>
            <p class="text-sm text-gray-500">Hiển thị {{ $sentSubmissions->total() }} báo cáo, kể cả đã chuyển tiếp.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Báo cáo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhóm</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Đang ở cấp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày gửi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phản hồi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($sentSubmissions as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900">{{ $s->detail->name ?? '—' }}</div>
                            <div class="text-xs text-gray-500">Năm học: {{ $s->academic_year ?? '—' }}</div>
                            <div class="text-xs">
                                <a target="_blank" href="{{ asset('storage/'.$s->file_path) }}" class="text-blue-600 hover:underline">Tải file</a>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm">{{ $s->group->name ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm">
                            @php
                                $stageNames = [
                                    'school' => 'Trường',
                                    'ward' => 'Xã/Phường',
                                    'province' => 'Tỉnh/Thành phố'
                                ];
                            @endphp
                            {{ $stageNames[$s->stage] ?? ucfirst($s->stage) }}
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusColor = [
                                    'submitted' => 'bg-blue-100 text-blue-800',
                                    'under_review' => 'bg-yellow-100 text-yellow-800',
                                    'returned' => 'bg-orange-100 text-orange-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                ][$s->status] ?? 'bg-slate-100 text-slate-800';
                            @endphp
                            @php
                                $statusNames = [
                                    'submitted' => 'Đã gửi',
                                    'under_review' => 'Đang xem xét',
                                    'returned' => 'Trả lại',
                                    'approved' => 'Đã duyệt',
                                    'rejected' => 'Từ chối'
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusColor }}">
                                {{ $statusNames[$s->status] ?? ucfirst($s->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ optional($s->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            @if($s->stage === 'school' && $s->feedback)
                                <button onclick="showFeedbackModal('{{ addslashes($s->feedback) }}', '{{ optional($s->feedback_at)->format('d/m/Y H:i') }}')" class="text-blue-600 hover:text-blue-900 underline">
                                    Xem phản hồi
                                </button>
                            @elseif($s->stage === 'school')
                                <span class="text-gray-400">—</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex flex-wrap gap-1">
                                @if(auth()->user() && auth()->user()->role_level === 'school' && ($s->stage ?? '') === 'school')
                                <form method="POST" action="{{ route('front.reports.search.management.submissions.forward', $s) }}" class="inline">
                                    @csrf
                                    <button class="px-2 py-1 bg-emerald-600 text-white rounded text-xs" title="Chuyển tiếp lên Xã/Phường">
                                        Chuyển tiếp
                                    </button>
                                </form>
                                @endif
                                
                                @if(auth()->user() && auth()->user()->id === $s->submitted_by_user_id && ($s->stage ?? '') === 'school')
                                <button onclick="openEditModal({{ $s->id }}, {{ $s->report_group_id }}, {{ $s->report_detail_id }}, '{{ $s->academic_year }}')" class="px-2 py-1 bg-blue-600 text-white rounded text-xs" title="Chỉnh sửa">
                                    Sửa
                                </button>
                                
                                <form method="POST" action="{{ route('front.reports.search.management.submissions.recall', $s) }}" class="inline" onsubmit="return confirm('Bạn có chắc chắn muốn thu hồi báo cáo này?')">
                                    @csrf
                                    <button class="px-2 py-1 bg-red-600 text-white rounded text-xs" title="Thu hồi báo cáo">
                                        Thu hồi
                                    </button>
                                </form>
                                @endif
                                
                                @if(!auth()->user() || auth()->user()->role_level !== 'school' || ($s->stage ?? '') !== 'school')
                                <span class="text-slate-400 text-xs">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        {{-- Pagination for sent submissions --}}
        @if($sentSubmissions && method_exists($sentSubmissions, 'hasPages') && $sentSubmissions->hasPages())
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            {{ $sentSubmissions->links() }}
        </div>
        @endif
    </div>
    @endif

    {{-- Province Overview Dashboard --}}
    @if(auth()->user() && auth()->user()->role_level === 'province' && isset($provinceStats))

    {{-- Hiệu suất các Xã/Phường --}}
    <div id="ward-performance" class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Hiệu suất các Xã/Phường</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 font-medium text-gray-700">Xã/Phường</th>
                        <th class="text-center py-3 px-4 font-medium text-gray-700">Tổng</th>
                        <th class="text-center py-3 px-4 font-medium text-gray-700">Đã duyệt</th>
                        <th class="text-center py-3 px-4 font-medium text-gray-700">Chờ xử lý</th>
                        <th class="text-center py-3 px-4 font-medium text-gray-700">Trả lại</th>
                        <th class="text-center py-3 px-4 font-medium text-gray-700">Tỷ lệ hoàn thành</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($provinceStats['ward_performance'] as $ward)
                    <tr class="border-b border-gray-100 hover:bg-gray-50">
                        <td class="py-3 px-4 font-medium text-gray-900">{{ $ward['ward_name'] }}</td>
                        <td class="py-3 px-4 text-center text-gray-700">{{ $ward['total'] }}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">{{ $ward['approved'] }}</span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">{{ $ward['pending'] }}</span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">{{ $ward['returned'] }}</span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex items-center justify-center">
                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $ward['completion_rate'] }}%"></div>
                                </div>
                                <span class="text-sm font-medium text-gray-700">{{ $ward['completion_rate'] }}%</span>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Report Groups Statistics --}}
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Thống kê theo nhóm báo cáo</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($provinceStats['group_stats'] as $groupId => $groupStat)
            <div class="p-4 border border-gray-200 rounded-lg">
                <h4 class="font-medium text-gray-900 mb-2">{{ $groupStat['name'] }}</h4>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tổng:</span>
                        <span class="font-medium">{{ $groupStat['count'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Đã duyệt:</span>
                        <span class="font-medium text-green-600">{{ $groupStat['approved'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Chờ xử lý:</span>
                        <span class="font-medium text-yellow-600">{{ $groupStat['pending'] }}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Province Reports Filter --}}
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-8">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Bộ lọc báo cáo</h3>
        <form method="GET" action="{{ route('front.reports.search.management.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nhóm báo cáo</label>
                <select name="province_reports_filter_group_id" id="province_reports_filter_group_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Tất cả nhóm báo cáo</option>
                    @foreach(($groups ?? []) as $group)
                        <option value="{{ $group->id }}" {{ request('province_reports_filter_group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Loại báo cáo</label>
                <select name="province_reports_filter_detail_id" id="province_reports_filter_detail_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Tất cả loại báo cáo</option>
                    @if(request('province_reports_filter_group_id'))
                        @php
                            $selectedGroup = $groups->find(request('province_reports_filter_group_id'));
                        @endphp
                        @if($selectedGroup && $selectedGroup->report_details)
                            @foreach($selectedGroup->report_details as $detail)
                                <option value="{{ $detail->id }}" {{ request('province_reports_filter_detail_id') == $detail->id ? 'selected' : '' }}>{{ $detail->name }}</option>
                            @endforeach
                        @endif
                    @endif
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Năm học</label>
                <select name="province_reports_filter_academic_year" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Tất cả năm học</option>
                    @php
                        $currentYear = date('Y');
                        $endYear = $currentYear + 1;
                        $currentAcademicYear = $currentYear . '-' . ($currentYear + 1);
                        $selectedYear = request('province_reports_filter_academic_year', $currentAcademicYear);
                        for ($year = $endYear; $year >= 2000; $year--) {
                            $academicYear = $year . '-' . ($year + 1);
                            $selected = ($academicYear === $selectedYear) ? 'selected' : '';
                            echo "<option value=\"{$academicYear}\" {$selected}>{$academicYear}</option>";
                        }
                    @endphp
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Trạng thái</label>
                <select name="province_reports_filter_status" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <option value="">Tất cả trạng thái</option>
                    <option value="submitted" {{ request('province_reports_filter_status') == 'submitted' ? 'selected' : '' }}>Đã gửi</option>
                    <option value="approved" {{ request('province_reports_filter_status') == 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                </select>
            </div>
            <div class="md:col-span-4 flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                    Lọc
                </button>
                <a href="{{ route('front.reports.search.management.index') }}" class="px-4 py-2 bg-gray-500 text-white rounded-lg text-sm hover:bg-gray-600">
                    Xóa lọc
                </a>
            </div>
        </form>
    </div>

    {{-- Move filters right after the overview for Province --}}
    <script>
        // Move filters block between Overview and Ward Performance for Province only
        (function(){
            try {
                const filters = document.getElementById('filters-block');
                const perf = document.getElementById('ward-performance');
                if (filters && perf && perf.parentElement) {
                    perf.parentElement.insertBefore(filters, perf);
                }
            } catch(e) {}
        })();
    </script>
    @endif

    {{-- Reports Table --}}
    @if(auth()->user() && auth()->user()->role_level === 'province' && isset($submissionsByWard))
    {{-- Province View: Grouped by Xã/Phường --}}
    <div class="space-y-6">
        @forelse($submissionsByWard as $wardId => $wardSubmissions)
        @php
            $ward = $wardSubmissions->first()->ward ?? null;
            $wardName = $ward ? $ward->name : "Xã/Phường ID: {$wardId}";
        @endphp
        
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-blue-50">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ $wardName }}
                        <span class="text-sm font-normal text-gray-600">({{ $wardSubmissions->count() }} báo cáo)</span>
                    </h3>
                    <div class="flex items-center space-x-2">
                        @php
                            $approvedCount = $wardSubmissions->where('status', 'approved')->count();
                            $pendingCount = $wardSubmissions->whereIn('status', ['submitted', 'under_review'])->count();
                            $returnedCount = $wardSubmissions->where('status', 'returned')->count();
                        @endphp
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">{{ $approvedCount }} đã duyệt</span>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">{{ $pendingCount }} chờ xử lý</span>
                        @if($returnedCount > 0)
                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">{{ $returnedCount }} trả lại</span>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 table-fixed">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-2/5 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Báo cáo</th>
                            <th class="w-1/6 px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Trường</th>
                            <th class="w-1/6 px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                            <th class="w-1/6 px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày gửi</th>
                            <th class="w-1/6 px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($wardSubmissions as $submission)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ $submission->detail->name ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">Nhóm: {{ $submission->group->name ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">Năm học: {{ $submission->academic_year ?? '—' }}</div>
                                <div class="text-xs">
                                    <a target="_blank" href="{{ asset('storage/' . $submission->file_path) }}" class="text-blue-600 hover:underline">Tải file</a>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900 text-center">{{ $submission->school->name ?? 'N/A' }}</td>
                            <td class="px-6 py-4 text-center">
                                @php
                                    $statusNames = [
                                        'submitted' => 'Đã gửi',
                                        'under_review' => 'Đang xem xét',
                                        'returned' => 'Trả lại',
                                        'approved' => 'Đã duyệt',
                                        'rejected' => 'Từ chối'
                                    ];
                                    $statusColors = [
                                        'submitted' => 'bg-blue-100 text-blue-800',
                                        'under_review' => 'bg-yellow-100 text-yellow-800',
                                        'returned' => 'bg-red-100 text-red-800',
                                        'approved' => 'bg-green-100 text-green-800',
                                        'rejected' => 'bg-red-100 text-red-800'
                                    ];
                                @endphp
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusColors[$submission->status] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $statusNames[$submission->status] ?? ucfirst($submission->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 text-center">{{ optional($submission->created_at)->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 text-sm text-center">
                                <div class="flex flex-wrap gap-1 justify-center">
                                    @if($submission->status === 'returned')
                                    <span class="text-slate-400 text-xs">Đã trả lại</span>
                                    @else
                                    <button onclick="openFeedbackModal('{{ route('front.reports.search.management.submissions.update-status', $submission) }}', 'Ward')" data-feedback="{{ $submission->feedback ?? '' }}" class="px-2 py-1 bg-red-600 text-white rounded text-xs" title="Trả lại cho Ward">
                                                Trả lại
                                            </button>
                                        
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.update-status', $submission) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                        <input type="hidden" name="feedback" value="">
                                            <button class="px-2 py-1 bg-green-600 text-white rounded text-xs" title="Duyệt báo cáo">
                                                Duyệt
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @empty
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-6 py-10 text-center text-sm text-gray-500">
                Chưa có báo cáo nào từ các Xã/Phường.
            </div>
        </div>
        @endforelse
    </div>
    @elseif(auth()->user() && auth()->user()->role_level === 'province')
    {{-- Normal View for Province only --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Danh sách báo cáo</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Báo cáo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nhóm</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cấp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Người nộp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hạn nộp</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngày tạo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phản hồi</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse(($submissions ?? []) as $submission)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $submission->detail->name ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">Năm học: {{ $submission->academic_year ?? '—' }}</div>
                                    <div class="text-xs"><a class="text-blue-600 hover:underline" target="_blank" href="{{ asset('storage/'.$submission->file_path) }}">Tải file</a></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $submission->group->name ?? '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            @php
                                $stageNames = [
                                    'school' => 'Trường',
                                    'ward' => 'Xã/Phường',
                                    'province' => 'Tỉnh/Thành phố'
                                ];
                            @endphp
                            {{ $stageNames[$submission->stage] ?? ucfirst($submission->stage) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusColor = [
                                    'submitted' => 'bg-blue-100 text-blue-800',
                                    'under_review' => 'bg-yellow-100 text-yellow-800',
                                    'returned' => 'bg-orange-100 text-orange-800',
                                    'approved' => 'bg-green-100 text-green-800',
                                    'rejected' => 'bg-red-100 text-red-800',
                                ][$submission->status] ?? 'bg-slate-100 text-slate-800';
                            @endphp
                            @php
                                $statusNames = [
                                    'submitted' => 'Đã gửi',
                                    'under_review' => 'Đang xem xét',
                                    'returned' => 'Trả lại',
                                    'approved' => 'Đã duyệt',
                                    'rejected' => 'Từ chối'
                                ];
                            @endphp
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusColor }}">
                                {{ $statusNames[$submission->status] ?? ucfirst($submission->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $submission->user->name ?? '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($submission->deadline)
                                @php
                                    $isOverdue = $submission->deadline < now()->toDateString() && $submission->status !== 'approved';
                                @endphp
                                <span class="{{ $isOverdue ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                    {{ $submission->deadline->format('d/m/Y') }}
                                </span>
                                @if($isOverdue)
                                    <span class="text-xs text-red-500">(Quá hạn)</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ optional($submission->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($submission->feedback)
                                <button onclick="showFeedback('{{ $submission->feedback }}', '{{ optional($submission->feedback_at)->format('d/m/Y H:i') }}')" class="text-blue-600 hover:text-blue-900 underline">
                                    Xem phản hồi
                                </button>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex flex-wrap gap-2 items-center">
                                @if(auth()->user() && auth()->user()->role_level === 'ward')
                                <div class="flex flex-wrap gap-1">
                                    @if($submission->status !== 'under_review')
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.update-status', $submission) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="under_review">
                                        <input type="hidden" name="feedback" value="">
                                        <button class="px-2 py-1 bg-yellow-600 text-white rounded text-xs" title="Đặt trạng thái đang xem xét">
                                            Đang xem
                                        </button>
                                    </form>
                                    @endif
                                    
                                    @if($submission->status !== 'approved')
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.update-status', $submission) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <input type="hidden" name="feedback" value="">
                                        <button class="px-2 py-1 bg-green-600 text-white rounded text-xs" title="Duyệt báo cáo">
                                            Duyệt
                                        </button>
                                    </form>
                                    @endif
                                </div>
                                @elseif(auth()->user() && auth()->user()->role_level !== 'school')
                                <form method="POST" action="{{ route('front.reports.search.management.submissions.update-status', $submission) }}" class="inline-flex items-center gap-2">
                                    @csrf
                                    <select name="status" class="px-2 py-1 border rounded text-xs">
                                        <option value="under_review" @selected($submission->status==='under_review')>Đang xem</option>
                                        <option value="returned" @selected($submission->status==='returned')>Trả lại</option>
                                        <option value="approved" @selected($submission->status==='approved')>Duyệt</option>
                                        <option value="rejected" @selected($submission->status==='rejected')>Từ chối</option>
                                    </select>
                                    <input type="hidden" name="feedback" value="">
                                    <button class="px-2 py-1 bg-slate-600 text-white rounded text-xs">Cập nhật</button>
                                </form>
                                @endif

                                @if(auth()->user() && auth()->user()->role_level === 'school' && $submission->stage === 'school')
                                <form method="POST" action="{{ route('front.reports.search.management.submissions.forward', $submission) }}">
                                    @csrf
                                    @if($submission->status === 'returned')
                                    <button class="px-2 py-1 bg-orange-600 text-white rounded text-xs" title="Nộp lại lên Xã/Phường">
                                        Nộp lại
                                    </button>
                                    @else
                                    <button class="px-2 py-1 bg-emerald-600 text-white rounded text-xs" title="Chuyển tiếp lên Xã/Phường">
                                        Chuyển tiếp
                                    </button>
                                    @endif
                                </form>
                                @elseif(auth()->user() && auth()->user()->role_level === 'ward' && $submission->stage === 'ward')
                                <div class="flex flex-wrap gap-1">
                                    @if($submission->status === 'returned')
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.forward', $submission) }}" class="inline">
                                        @csrf
                                        <button class="px-2 py-1 bg-orange-600 text-white rounded text-xs" title="Nộp lại lên Tỉnh/Thành phố">
                                            Nộp lại
                                        </button>
                                    </form>
                                    @else
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.forward', $submission) }}" class="inline">
                                        @csrf
                                        <button class="px-2 py-1 bg-emerald-600 text-white rounded text-xs" title="Chuyển tiếp lên Tỉnh/Thành phố">
                                            Chuyển tiếp
                                        </button>
                                    </form>
                                    
                                    <button onclick="openFeedbackModal('{{ route('front.reports.search.management.submissions.update-status', $submission) }}', 'School')" data-feedback="{{ $submission->feedback ?? '' }}" class="px-2 py-1 bg-red-600 text-white rounded text-xs" title="Trả lại cho School">
                                            Trả lại
                                        </button>
                                    @endif
                                </div>
                                @elseif(auth()->user() && auth()->user()->role_level === 'province' && $submission->stage === 'province')
                                <div class="flex flex-wrap gap-1">
                                    @if($submission->status === 'returned')
                                    <span class="text-slate-400 text-xs">Đã trả lại</span>
                                    @else
                                    <button onclick="openFeedbackModal('{{ route('front.reports.search.management.submissions.update-status', $submission) }}', 'Ward')" data-feedback="{{ $submission->feedback ?? '' }}" class="px-2 py-1 bg-red-600 text-white rounded text-xs" title="Trả lại cho Ward">
                                                Trả lại
                                            </button>
                                        
                                    <form method="POST" action="{{ route('front.reports.search.management.submissions.update-status', $submission) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="status" value="approved">
                                        <input type="hidden" name="feedback" value="">
                                            <button class="px-2 py-1 bg-green-600 text-white rounded text-xs" title="Duyệt báo cáo">
                                                Duyệt
                                            </button>
                                        </form>
                                    @endif
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">Chưa có báo cáo nào.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(auth()->user() && auth()->user()->role_level === 'school' && isset($sentSubmissions) && $sentSubmissions->isNotEmpty())
        
        @endif

        {{-- Pagination --}}
        <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            @if(isset($submissions) && method_exists($submissions, 'hasPages') && $submissions->hasPages())
                {{ $submissions->links() }}
            @endif
        </div>
    </div>
    @endif

    {{-- Feedback Modal for Return Action --}}
    <div id="feedbackModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Trả lại báo cáo</h3>
                    <button onclick="closeFeedbackModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <form id="feedbackForm" method="POST">
                    @csrf
                    <input type="hidden" name="status" value="rejected">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Phản hồi</label>
                        <textarea name="feedback" id="feedbackText" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="Nhập lý do trả lại báo cáo (không bắt buộc)..."></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-3">
                        <button type="button" onclick="closeFeedbackModal()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                            Hủy
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Xác nhận trả lại
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>

    {{-- Feedback View Modal --}}
    <div id="feedbackViewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Phản hồi từ cấp trên</h3>
                    <button onclick="closeFeedbackViewModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Thời gian phản hồi</label>
                    <p id="feedbackTime" class="text-sm text-gray-600 mb-4"></p>
                    
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nội dung phản hồi</label>
                    <div id="feedbackContent" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 min-h-[100px] whitespace-pre-wrap"></div>
                </div>
                
                <div class="flex justify-end">
                    <button onclick="closeFeedbackViewModal()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
</div>

{{-- Modal tạo báo cáo đã được loại bỏ theo yêu cầu --}}

{{-- Modal chỉnh sửa báo cáo --}}
<div id="editModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Chỉnh sửa báo cáo</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <form id="editForm" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="editSubmissionId" name="submission_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nhóm báo cáo</label>
                    <select name="report_group_id" id="editReportGroup" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Chọn nhóm báo cáo</option>
                        @foreach(($groups ?? []) as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Loại báo cáo</label>
                    <select name="report_detail_id" id="editReportDetail" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Chọn loại báo cáo</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Năm học</label>
                    <select name="academic_year" id="editAcademicYear" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Chọn năm học</option>
                        @php
                            $currentYear = date('Y');
                            $endYear = $currentYear + 1;
                            for ($year = $endYear; $year >= 2000; $year--) {
                                $academicYear = $year . '-' . ($year + 1);
                                echo "<option value=\"{$academicYear}\">{$academicYear}</option>";
                            }
                        @endphp
                    </select>
                </div>

                

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">File báo cáo (tùy chọn)</label>
                    <input type="file" name="file" accept=".xlsx,.xls,.pdf" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Để trống nếu không muốn thay đổi file</p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                        Hủy
                    </button>
                    <button type="button" onclick="submitEditForm()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Ward Edit Modal --}}
<div id="wardEditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Sửa báo cáo</h3>
            <form id="wardEditForm" method="POST" action="#" enctype="multipart/form-data">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nhóm báo cáo</label>
                    <select id="wardEditReportGroup" name="report_group_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Chọn nhóm báo cáo</option>
                        @foreach(($groups ?? []) as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Loại báo cáo</label>
                    <select id="wardEditReportDetail" name="report_detail_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Chọn loại báo cáo</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Năm học</label>
                    <select id="wardEditAcademicYear" name="academic_year" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Chọn năm học</option>
                        @php
                            $currentYear = date('Y');
                            $endYear = $currentYear + 1;
                            for ($year = $endYear; $year >= 2000; $year--) {
                                $academicYear = $year . '-' . ($year + 1);
                                echo "<option value=\"{$academicYear}\">{$academicYear}</option>";
                            }
                        @endphp
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">File báo cáo mới (tùy chọn)</label>
                    <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Để trống nếu không muốn thay đổi file</p>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="closeWardEditModal()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        Hủy
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Ward Feedback Modal --}}
<div id="wardFeedbackModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Phản hồi từ cấp trên</h3>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Thời gian phản hồi</label>
                <p id="wardFeedbackTime" class="text-sm text-gray-600 mb-2">-</p>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nội dung phản hồi</label>
                <div id="wardFeedbackContent" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 min-h-[100px] text-sm text-gray-700">
                    Đang tải...
                </div>
            </div>
            <div class="flex justify-end">
                <button type="button" onclick="closeWardFeedbackModal()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    Đóng
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Bulk actions for deadlines
function toggleAllDeadlines() {
    const selectAll = document.getElementById('selectAllDeadlines');
    const checkboxes = document.querySelectorAll('.deadline-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

// Handle bulk action with proper form submission
function handleBulkAction(action, confirmMessage) {
    const checkedBoxes = document.querySelectorAll('.deadline-checkbox:checked');
    const allCheckboxes = document.querySelectorAll('.deadline-checkbox');
    
    console.log('Bulk action debug:', {
        action: action,
        checkedBoxes: checkedBoxes.length,
        allCheckboxes: allCheckboxes.length,
        checkedIds: Array.from(checkedBoxes).map(cb => cb.value),
        allIds: Array.from(allCheckboxes).map(cb => cb.value)
    });
    
    if (checkedBoxes.length === 0) {
        alert('Vui lòng chọn ít nhất một hạn nộp để thực hiện thao tác.');
        return false;
    }
    
    if (confirm(confirmMessage)) {
        // Create a hidden input for the action
        const form = document.getElementById('bulkDeadlineForm');
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);
        
        console.log('Submitting form with:', {
            action: action,
            selectedIds: Array.from(checkedBoxes).map(cb => cb.value)
        });
        
        // Submit the form
        form.submit();
    }
}

// Submit bulk action with validation
function submitBulkAction(action, confirmMessage) {
    const checkedBoxes = document.querySelectorAll('.deadline-checkbox:checked');
    
    console.log('Bulk action debug:', {
        action: action,
        checkedBoxes: checkedBoxes.length,
        checkboxes: document.querySelectorAll('.deadline-checkbox').length
    });
    
    if (checkedBoxes.length === 0) {
        alert('Vui lòng chọn ít nhất một hạn nộp để thực hiện thao tác.');
        return false;
    }
    
    return confirm(confirmMessage);
}

// Update select all checkbox when individual checkboxes change
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.deadline-checkbox');
    const selectAll = document.getElementById('selectAllDeadlines');
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.deadline-checkbox:checked').length;
            const totalCount = checkboxes.length;
            
            if (checkedCount === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else if (checkedCount === totalCount) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            }
        });
    });
});

// Deadline tabs (Province)
function switchDeadlineTab(which) {
    const form = document.getElementById('ward-form');
    const list = document.getElementById('ward-list');
    const b1 = document.getElementById('btn-ward-form');
    const b2 = document.getElementById('btn-ward-list');
    if (!form || !list) return;
    if (which === 'ward-form') {
        form.classList.remove('hidden');
        form.classList.add('block');
        list.classList.add('hidden');
        b1.className = 'px-3 py-1 text-sm bg-blue-600 text-white rounded-l';
        b2.className = 'px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-r';
        try { localStorage.setItem('provinceDeadlineTab', 'ward-form'); } catch(e) {}
    } else {
        form.classList.add('hidden');
        list.classList.remove('hidden');
        b1.className = 'px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-l';
        b2.className = 'px-3 py-1 text-sm bg-blue-600 text-white rounded-r';
        // scroll to list
        list.scrollIntoView({ behavior: 'smooth', block: 'start' });
        try { localStorage.setItem('provinceDeadlineTab', 'ward-list'); } catch(e) {}
    }
}

// Deadline tabs (Ward)
function switchSchoolDeadlineTab(which) {
    const form = document.getElementById('school-form');
    const list = document.getElementById('school-list');
    const b1 = document.getElementById('btn-school-form');
    const b2 = document.getElementById('btn-school-list');
    if (!form || !list) return;
    if (which === 'school-form') {
        form.classList.remove('hidden');
        form.classList.add('block');
        list.classList.add('hidden');
        b1.className = 'px-3 py-1 text-sm bg-blue-600 text-white rounded-l';
        b2.className = 'px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-r';
        try { localStorage.setItem('wardDeadlineTab', 'school-form'); } catch(e) {}
    } else {
        form.classList.add('hidden');
        list.classList.remove('hidden');
        b1.className = 'px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded-l';
        b2.className = 'px-3 py-1 text-sm bg-blue-600 text-white rounded-r';
        list.scrollIntoView({ behavior: 'smooth', block: 'start' });
        try { localStorage.setItem('wardDeadlineTab', 'school-list'); } catch(e) {}
    }
}

// Restore tabs on load and ensure pagination keeps active tab
document.addEventListener('DOMContentLoaded', function() {
    try {
        const lvl = document.body?.dataset?.roleLevel || (window.AppRoleLevel || '');
        const provinceTab = localStorage.getItem('provinceDeadlineTab');
        if (provinceTab === 'ward-list' && document.getElementById('ward-list')) {
            switchDeadlineTab('ward-list');
        }
        const wardTab = localStorage.getItem('wardDeadlineTab');
        if (wardTab === 'school-list' && document.getElementById('school-list')) {
            switchSchoolDeadlineTab('school-list');
        }
        // Hook pagination links inside ward-list to persist tab
        const wardList = document.getElementById('ward-list');
        if (wardList) {
            wardList.querySelectorAll('.mt-4 a').forEach(a => {
                a.addEventListener('click', () => {
                    try { localStorage.setItem('provinceDeadlineTab', 'ward-list'); } catch(e) {}
                });
            });
        }
    } catch(e) {}
});
// Dependent select for report details
const GROUPS = @json(($groups ?? []));
document.addEventListener('DOMContentLoaded', function() {
    const groupSelect = document.getElementById('report_group_id');
    const detailSelect = document.getElementById('report_detail_id');
    if (groupSelect && detailSelect) {
        groupSelect.addEventListener('change', function() {
            const gid = this.value;
            while (detailSelect.firstChild) detailSelect.removeChild(detailSelect.firstChild);
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Chọn loại báo cáo';
            detailSelect.appendChild(placeholder);
            const group = GROUPS.find(g => String(g.id) === String(gid));
            if (group && Array.isArray(group.report_details)) {
                group.report_details.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name;
                    detailSelect.appendChild(opt);
                });
            }
        });
    }

    // Default to single tab on load
    try { toggleUploadMode('single'); } catch(e) {}
});

// Report action functions
function viewReport(id) {
    // Implement view report functionality
    console.log('View report:', id);
    // You can redirect to report view page or open a modal
    window.location.href = `/reports/${id}/view`;
}

function editReport(id) {
    // Implement edit report functionality
    console.log('Edit report:', id);
    window.location.href = `/reports/${id}/edit`;
}

function exportReport(id) {
    // Implement export report functionality
    console.log('Export report:', id);
    // You can trigger download or redirect to export endpoint
    window.location.href = `/reports/${id}/export`;
}

function deleteReport(id) {
    if (confirm('Bạn có chắc chắn muốn xóa báo cáo này?')) {
        // Implement delete report functionality
        console.log('Delete report:', id);
        // You can make an AJAX request to delete the report
        fetch(`/reports/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
            },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Xóa báo cáo thành công!', 'success');
                location.reload();
            } else {
                showNotification('Có lỗi xảy ra khi xóa báo cáo', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Có lỗi xảy ra khi xóa báo cáo', 'error');
        });
    }
}


// Notification function
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        'bg-blue-500'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Filter dependent select for report details
document.addEventListener('DOMContentLoaded', function() {
    const groupSelect = document.getElementById('filter_report_group_id');
    const detailSelect = document.getElementById('filter_report_detail_id');
    
    if (groupSelect && detailSelect) {
        groupSelect.addEventListener('change', function() {
            const groupId = this.value;
            
            // Clear detail select
            detailSelect.innerHTML = '<option value="">Tất cả loại báo cáo</option>';
            
            if (groupId) {
                // Find the selected group and populate details
                const groups = @json($groups ?? []);
                const selectedGroup = groups.find(g => g.id == groupId);
                
                if (selectedGroup && selectedGroup.report_details) {
                    selectedGroup.report_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = detail.name;
                        detailSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Trigger change event on page load if group is already selected
        if (groupSelect.value) {
            groupSelect.dispatchEvent(new Event('change'));
        }
    }
    
    // School filter dependent select for report details
    const schoolGroupSelect = document.getElementById('school_filter_report_group_id');
    const schoolDetailSelect = document.getElementById('school_filter_report_detail_id');
    
    if (schoolGroupSelect && schoolDetailSelect) {
        schoolGroupSelect.addEventListener('change', function() {
            const groupId = this.value;
            
            // Clear detail select
            schoolDetailSelect.innerHTML = '<option value="">Tất cả loại báo cáo</option>';
            
            if (groupId) {
                // Find the selected group and populate details
                const groups = @json($groups ?? []);
                const selectedGroup = groups.find(g => g.id == groupId);
                
                if (selectedGroup && selectedGroup.report_details) {
                    selectedGroup.report_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = detail.name;
                        schoolDetailSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Trigger change event on page load if group is already selected
        if (schoolGroupSelect.value) {
            schoolGroupSelect.dispatchEvent(new Event('change'));
        }
    }
    
    // Deadline dependent select for report details
    const deadlineGroupSelect = document.getElementById('deadline_report_group_id');
    const deadlineDetailSelect = document.getElementById('deadline_report_detail_id');
    
    if (deadlineGroupSelect && deadlineDetailSelect) {
        deadlineGroupSelect.addEventListener('change', function() {
            const groupId = this.value;
            
            // Clear detail select
            deadlineDetailSelect.innerHTML = '<option value="">Tất cả loại</option>';
            
            if (groupId) {
                // Find the selected group and populate details
                const groups = @json($groups ?? []);
                const selectedGroup = groups.find(g => g.id == groupId);
                
                if (selectedGroup && selectedGroup.report_details) {
                    selectedGroup.report_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = detail.name;
                        deadlineDetailSelect.appendChild(option);
                    });
                }
            }
        });
    }
    
    // Remove auto-submit behavior - only submit when user clicks "Lọc" button
});

// Upload mode toggle
function toggleUploadMode(mode) {
    const singleForm = document.getElementById('singleUploadForm');
    const multiBlock = document.getElementById('multiUploadBlock');
    const singleBtn = document.getElementById('singleModeBtn');
    const multiBtn = document.getElementById('multiModeBtn');

    if (singleForm) singleForm.classList.add('hidden');
    if (multiBlock) multiBlock.classList.add('hidden');

    if (singleBtn) singleBtn.className = 'px-3 py-1 text-sm bg-gray-100 text-gray-700';
    if (multiBtn) multiBtn.className = 'px-3 py-1 text-sm bg-gray-100 text-gray-700';

    if (mode === 'single' && singleForm) {
        singleForm.classList.remove('hidden');
        if (singleBtn) singleBtn.className = 'px-3 py-1 text-sm bg-blue-600 text-white';
    } else if (mode === 'multi' && multiBlock) {
        multiBlock.classList.remove('hidden');
        if (multiBtn) multiBtn.className = 'px-3 py-1 text-sm bg-blue-600 text-white';
        // ensure at least one item exists
        try {
            const container = document.getElementById('multiFormContainer');
            if (container && container.children.length === 0 && typeof createItem === 'function') {
                const first = createItem(0);
                container.appendChild(first);
            }
        } catch(e) {}
    }
}

// Generate academic year options for dropdown
function generateAcademicYearOptions() {
    const currentYear = new Date().getFullYear();
    const endYear = currentYear + 1;
    const currentAcademicYear = currentYear + '-' + (currentYear + 1);
    let options = '';
    
    for (let year = endYear; year >= 2000; year--) {
        const academicYear = year + '-' + (year + 1);
        const selected = (academicYear === currentAcademicYear) ? ' selected' : '';
        options += `<option value="${academicYear}"${selected}>${academicYear}</option>`;
    }
    
    return options;
}

// Multi-form dynamic items
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('multiFormContainer');
    const addBtn = document.getElementById('addMultiItemBtn');
    if (!container || !addBtn) return;

    const MAX_ITEMS = 10;

    function createItem(index) {
        const wrap = document.createElement('div');
        wrap.className = 'grid grid-cols-1 md:grid-cols-12 gap-3 p-3 border rounded';
        wrap.innerHTML = `
            <div class="md:col-span-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Nhóm</label>
                <select name="submissions[${index}][report_group_id]" class="w-full px-3 py-2 border rounded" required>
                    <option value="">Chọn nhóm</option>
                    ${(GROUPS || []).map(g => `<option value="${g.id}">${g.name}</option>`).join('')}
                </select>
            </div>
            <div class="md:col-span-4">
                <label class="block text-xs font-medium text-gray-600 mb-1">Loại</label>
                <select name="submissions[${index}][report_detail_id]" class="w-full px-3 py-2 border rounded" required>
                    <option value="">Chọn loại báo cáo</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">Năm học</label>
                <select name="submissions[${index}][academic_year]" class="w-full px-3 py-2 border rounded">
                    <option value="">Chọn năm học</option>
                    ${generateAcademicYearOptions()}
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-600 mb-1">File</label>
                <input type="file" name="submissions[${index}][file]" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.zip" class="w-full px-3 py-2 border rounded" required />
            </div>
            <div class="md:col-span-1 flex items-end justify-end">
                <button type="button" class="px-2 py-1 bg-red-600 text-white rounded text-xs removeItemBtn">Xóa</button>
            </div>
        `;

        // dependent select (group -> details)
        const groupSelect = wrap.querySelector(`select[name="submissions[${index}][report_group_id]"]`);
        const detailSelect = wrap.querySelector(`select[name="submissions[${index}][report_detail_id]"]`);
        if (groupSelect && detailSelect) {
            groupSelect.addEventListener('change', function() {
                const gid = String(this.value);
                detailSelect.innerHTML = '<option value="">Chọn loại báo cáo</option>';
                const group = (GROUPS || []).find(g => String(g.id) === gid);
                if (group && Array.isArray(group.report_details)) {
                    group.report_details.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = d.name;
                        detailSelect.appendChild(opt);
                    });
                }
            });
        }

        // remove item
        wrap.querySelector('.removeItemBtn').addEventListener('click', function() {
            wrap.remove();
            renumber();
        });

        return wrap;
    }

    function renumber() {
        const items = Array.from(container.children);
        items.forEach((el, idx) => {
            el.querySelectorAll('select, input').forEach(input => {
                const n = input.getAttribute('name');
                if (!n) return;
                const newName = n.replace(/submissions\[[0-9]+\]/, `submissions[${idx}]`);
                input.setAttribute('name', newName);
            });
        });
    }

    addBtn.addEventListener('click', function() {
        if (container.children.length >= MAX_ITEMS) {
            alert('Tối đa 10 mục mỗi lần.');
            return;
        }
        const item = createItem(container.children.length);
        container.appendChild(item);
    });

    // Do not auto-add by default; will add one when switching to multi tab
});

// Show feedback modal
function showFeedback(feedback, feedbackDate) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
    modal.innerHTML = `
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Phản hồi từ cấp trên</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Thời gian: ${feedbackDate}</p>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-800">${feedback}</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

// Edit modal functions
function openEditModal(submissionId, reportGroupId, reportDetailId, academicYear) {
    // Show the edit modal
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editSubmissionId').value = submissionId;
    
    // Pre-populate current data
    document.getElementById('editForm').reset();
    document.getElementById('editSubmissionId').value = submissionId;
    
    // Set report group
    document.getElementById('editReportGroup').value = reportGroupId;
    
    // Trigger change event to populate report details
    document.getElementById('editReportGroup').dispatchEvent(new Event('change'));
    
    // Wait a bit for the details to populate, then set the detail
    setTimeout(() => {
        document.getElementById('editReportDetail').value = reportDetailId;
    }, 100);
    
    // Set academic year
    document.getElementById('editAcademicYear').value = academicYear;
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function submitEditForm() {
    const form = document.getElementById('editForm');
    const submissionId = document.getElementById('editSubmissionId').value;
    form.action = `/tra-cuu-thong-ke/quan-ly/submissions/${submissionId}/edit`;
    form.submit();
}

// Handle dependent select in edit modal
document.addEventListener('DOMContentLoaded', function() {
    const editGroupSelect = document.getElementById('editReportGroup');
    const editDetailSelect = document.getElementById('editReportDetail');
    
    if (editGroupSelect && editDetailSelect) {
        editGroupSelect.addEventListener('change', function() {
            const groupId = this.value;
            
            // Clear detail select
            editDetailSelect.innerHTML = '<option value="">Chọn loại báo cáo</option>';
            
            if (groupId) {
                // Find the selected group and populate details
                const selectedGroup = GROUPS.find(g => g.id == groupId);
                
                if (selectedGroup && selectedGroup.report_details) {
                    selectedGroup.report_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = detail.name;
                        editDetailSelect.appendChild(option);
                    });
                }
            }
        });
    }
});

// Province reports filter dependent select for report details
document.addEventListener('DOMContentLoaded', function() {
    const provinceReportsGroupSelect = document.getElementById('province_reports_filter_group_id');
    const provinceReportsDetailSelect = document.getElementById('province_reports_filter_detail_id');
    
    if (provinceReportsGroupSelect && provinceReportsDetailSelect) {
        provinceReportsGroupSelect.addEventListener('change', function() {
            const groupId = this.value;
            
            // Clear detail select
            provinceReportsDetailSelect.innerHTML = '<option value="">Tất cả loại báo cáo</option>';
            
            if (groupId) {
                // Find the selected group and populate details
                const groups = @json($groups ?? []);
                const selectedGroup = groups.find(g => g.id == groupId);
                
                if (selectedGroup && selectedGroup.report_details) {
                    selectedGroup.report_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = detail.name;
                        provinceReportsDetailSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Trigger change event on page load if group is already selected
        if (provinceReportsGroupSelect.value) {
            provinceReportsGroupSelect.dispatchEvent(new Event('change'));
        }
    }
});

// School deadline dependent select for report details
document.addEventListener('DOMContentLoaded', function() {
    const schoolGroupSelect = document.querySelector('select[name="report_group_id"]');
    const schoolDetailSelect = document.getElementById('school_report_detail_id');
    
    if (schoolGroupSelect && schoolDetailSelect) {
        schoolGroupSelect.addEventListener('change', function() {
            const groupId = this.value;
            
            // Clear detail select
            schoolDetailSelect.innerHTML = '<option value="">Tất cả loại</option>';
            
            if (groupId) {
                // Find the selected group and populate details
                const groups = @json($groups ?? []);
                const selectedGroup = groups.find(g => g.id == groupId);
                
                if (selectedGroup && selectedGroup.report_details) {
                    selectedGroup.report_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = detail.name;
                        schoolDetailSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Trigger change event on page load if group is already selected
        if (schoolGroupSelect.value) {
            schoolGroupSelect.dispatchEvent(new Event('change'));
        }
    }
});

// School deadline bulk actions
function toggleAllSchoolDeadlines() {
    const selectAll = document.getElementById('selectAllSchoolDeadlines');
    const checkboxes = document.querySelectorAll('.school-deadline-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
    });
}

function handleSchoolBulkAction(action, confirmMessage) {
    const checkboxes = document.querySelectorAll('.school-deadline-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Vui lòng chọn ít nhất một hạn nộp để thực hiện thao tác.');
        return;
    }
    
    if (confirm(confirmMessage)) {
        const form = document.getElementById('bulkSchoolDeadlineForm');
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;
        form.appendChild(actionInput);
        form.submit();
    }
}

// School deadline year filter
document.addEventListener('DOMContentLoaded', function() {
    const yearFilter = document.getElementById('schoolDeadlineYearFilter');
    if (yearFilter) {
        yearFilter.addEventListener('change', function() {
            const selectedYear = this.value;
            const url = new URL(window.location);
            
            if (selectedYear) {
                url.searchParams.set('school_deadline_year', selectedYear);
            } else {
                url.searchParams.delete('school_deadline_year');
            }
            
            window.location.href = url.toString();
        });
    }
});

// Ward deadline year filter
document.addEventListener('DOMContentLoaded', function() {
    const wardYearFilter = document.getElementById('wardDeadlineYearFilter');
    if (wardYearFilter) {
        wardYearFilter.addEventListener('change', function() {
            const selectedYear = this.value;
            const url = new URL(window.location);
            
            if (selectedYear) {
                url.searchParams.set('ward_deadline_year', selectedYear);
            } else {
                url.searchParams.delete('ward_deadline_year');
            }
            
            window.location.href = url.toString();
        });
    }
});

// Ward upload functionality
function toggleWardUploadMode(mode) {
    const singleBtn = document.getElementById('wardSingleModeBtn');
    const multiBtn = document.getElementById('wardMultiModeBtn');
    const singleForm = document.getElementById('wardSingleUploadForm');
    const multiForm = document.getElementById('wardMultiUploadForm');
    
    if (mode === 'single') {
        singleBtn.className = 'px-3 py-1 text-sm bg-blue-600 text-white';
        multiBtn.className = 'px-3 py-1 text-sm bg-gray-100 text-gray-700';
        singleForm.classList.remove('hidden');
        multiForm.classList.add('hidden');
    } else {
        singleBtn.className = 'px-3 py-1 text-sm bg-gray-100 text-gray-700';
        multiBtn.className = 'px-3 py-1 text-sm bg-blue-600 text-white';
        singleForm.classList.add('hidden');
        multiForm.classList.remove('hidden');
    }
}

function addWardUploadItem() {
    const container = document.getElementById('wardUploadItems');
    const itemCount = container.children.length;
    
    const itemDiv = document.createElement('div');
    itemDiv.className = 'border border-gray-200 rounded-lg p-4 mb-4';
    itemDiv.innerHTML = `
        <div class="flex items-center justify-between mb-3">
            <h5 class="text-sm font-medium text-gray-700">Báo cáo ${itemCount + 1}</h5>
            <button type="button" onclick="removeWardUploadItem(this)" class="text-red-600 hover:text-red-800 text-sm">
                Xóa
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nhóm báo cáo</label>
                <select name="ward_multi_report_group_id[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    <option value="">Chọn nhóm</option>
                    @foreach(($groups ?? []) as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Loại báo cáo</label>
                <select name="ward_multi_report_detail_id[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Chọn loại</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Năm học</label>
                <select name="ward_multi_academic_year[]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    <option value="">Chọn năm</option>
                    ${generateAcademicYearOptions()}
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">File</label>
                <input type="file" name="ward_multi_file[]" accept=".pdf,.doc,.docx,.xls,.xlsx" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
            </div>
        </div>
    `;
    
    container.appendChild(itemDiv);
}

function removeWardUploadItem(button) {
    button.closest('.border').remove();
}

// Ward dependent select for report details
document.addEventListener('DOMContentLoaded', function() {
    const wardGroupSelect = document.getElementById('ward_report_group_id');
    const wardDetailSelect = document.getElementById('ward_report_detail_id');
    
    if (wardGroupSelect && wardDetailSelect) {
        wardGroupSelect.addEventListener('change', function() {
            const groupId = this.value;
            
            // Clear detail select
            wardDetailSelect.innerHTML = '<option value="">Chọn loại báo cáo</option>';
            
            if (groupId) {
                // Find the selected group and populate details
                const groups = @json($groups ?? []);
                const selectedGroup = groups.find(g => g.id == groupId);
                
                if (selectedGroup && selectedGroup.report_details) {
                    selectedGroup.report_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = detail.name;
                        wardDetailSelect.appendChild(option);
                    });
                }
            }
        });
        
        // Trigger change event on page load if group is already selected
        if (wardGroupSelect.value) {
            wardGroupSelect.dispatchEvent(new Event('change'));
        }
    }
});

// Ward Edit Modal Functions
function openWardEditModal(submissionId, reportGroupId, reportDetailId, academicYear) {
    const modal = document.getElementById('wardEditModal');
    const form = document.getElementById('wardEditForm');
    const groupSelect = document.getElementById('wardEditReportGroup');
    const detailSelect = document.getElementById('wardEditReportDetail');
    const yearSelect = document.getElementById('wardEditAcademicYear');
    
    // Set form action
    form.action = `/tra-cuu-thong-ke/quan-ly/submissions/${submissionId}/update-ward`;
    
    // Set values
    groupSelect.value = reportGroupId;
    yearSelect.value = academicYear;
    
    // Trigger group change to populate details
    groupSelect.dispatchEvent(new Event('change'));
    
    // Set detail value after a short delay to ensure options are loaded
    setTimeout(() => {
        detailSelect.value = reportDetailId;
    }, 100);
    
    modal.classList.remove('hidden');
}

function closeWardEditModal() {
    const modal = document.getElementById('wardEditModal');
    modal.classList.add('hidden');
}

// Ward edit modal dependent select
document.addEventListener('DOMContentLoaded', function() {
    const wardEditGroupSelect = document.getElementById('wardEditReportGroup');
    const wardEditDetailSelect = document.getElementById('wardEditReportDetail');
    
    if (wardEditGroupSelect && wardEditDetailSelect) {
        wardEditGroupSelect.addEventListener('change', function() {
            const groupId = this.value;
            
            // Clear detail select
            wardEditDetailSelect.innerHTML = '<option value="">Chọn loại báo cáo</option>';
            
            if (groupId) {
                // Find the selected group and populate details
                const groups = @json($groups ?? []);
                const selectedGroup = groups.find(g => g.id == groupId);
                
                if (selectedGroup && selectedGroup.report_details) {
                    selectedGroup.report_details.forEach(detail => {
                        const option = document.createElement('option');
                        option.value = detail.id;
                        option.textContent = detail.name;
                        wardEditDetailSelect.appendChild(option);
                    });
                }
            }
        });
    }
});

// Ward Feedback Modal Functions
function openWardFeedbackModal(feedbackUrl) {
    const modal = document.getElementById('wardFeedbackModal');
    const contentDiv = document.getElementById('wardFeedbackContent');
    const timeDiv = document.getElementById('wardFeedbackTime');
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Show loading
    contentDiv.textContent = 'Đang tải...';
    timeDiv.textContent = '-';
    
    // Fetch feedback data
    fetch(feedbackUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                contentDiv.textContent = data.feedback;
                // You can add timestamp logic here if needed
                timeDiv.textContent = 'Vừa xong'; // Placeholder - you can improve this
            } else {
                contentDiv.textContent = 'Không thể tải phản hồi.';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contentDiv.textContent = 'Có lỗi xảy ra khi tải phản hồi.';
        });
}

function closeWardFeedbackModal() {
    const modal = document.getElementById('wardFeedbackModal');
    modal.classList.add('hidden');
}

// Feedback Modal Functions
function openFeedbackModal(actionUrl, targetLevel) {
    const modal = document.getElementById('feedbackModal');
    const form = document.getElementById('feedbackForm');
    const feedbackText = document.getElementById('feedbackText');
    
    // Set form action
    form.action = actionUrl;
    
    // Get existing feedback from the clicked button's data attribute
    const clickedButton = event.target;
    const existingFeedback = clickedButton.getAttribute('data-feedback') || '';
    
    // Set existing feedback if provided
    feedbackText.value = existingFeedback;
    
    // Show modal
    modal.classList.remove('hidden');
    
    // Focus on textarea
    setTimeout(() => {
        feedbackText.focus();
    }, 100);
}

function closeFeedbackModal() {
    const modal = document.getElementById('feedbackModal');
    modal.classList.add('hidden');
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('feedbackModal');
    if (event.target === modal) {
        closeFeedbackModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeFeedbackModal();
        closeFeedbackViewModal();
    }
});

// Feedback View Modal Functions
function showFeedbackModal(feedback, feedbackTime) {
    const modal = document.getElementById('feedbackViewModal');
    const feedbackContent = document.getElementById('feedbackContent');
    const feedbackTimeElement = document.getElementById('feedbackTime');
    
    // Set content
    feedbackContent.textContent = feedback;
    feedbackTimeElement.textContent = feedbackTime;
    
    // Show modal
    modal.classList.remove('hidden');
}

function closeFeedbackViewModal() {
    const modal = document.getElementById('feedbackViewModal');
    modal.classList.add('hidden');
}

// Close feedback view modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('feedbackViewModal');
    if (event.target === modal) {
        closeFeedbackViewModal();
    }
});
</script>
@endpush
