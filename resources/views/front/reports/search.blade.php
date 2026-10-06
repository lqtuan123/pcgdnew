@extends('layouts.front')
@section('title', 'Tra cứu thống kê')
@section('description', 'Tra cứu và xem các báo cáo thống kê giáo dục trực tiếp trên web')

@section('content')
<div class="min-h-screen bg-slate-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-slate-900 tracking-tight">Tra cứu thống kê</h1>
            <p class="mt-1 text-slate-600">Tìm kiếm, xem trực tiếp biểu mẫu trên web hoặc tải báo cáo thống kê giáo dục theo năm học và địa phương</p>
        </div>

        <!-- Filter Form -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
            <form id="searchForm" class="space-y-6"
                method="GET"
                action="{{ route('front.reports.searchreport') }}">

                <!-- Năm học -->
                <div class="grid gap-6 md:grid-cols-12">
                    <div class="md:col-span-3">
                        <label for="year" class="block text-sm font-semibold text-slate-700 mb-2">Năm học <span class="text-rose-500">*</span></label>
                        <select id="year" name="year"
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Chọn năm học —</option>
                            @php
                                $base = \App\Models\SurveyForm::currentAcademicYear();
                                [$y1,$y2] = explode('-', $base);
                                $years = [($y1-2).'-'.($y1-1), ($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1), ($y2+1).'-'.($y2+2)];
                            @endphp
                            @foreach($years as $yy)
                                <option value="{{ $yy }}" @selected(request('year',$base)===$yy)>{{ $yy }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Địa bàn -->
                <div class="md:col-span-12">
                   @include('partials.front.geo-filter', [
                        'scheme'          => 'new',
                        'schemeMode'      => 'new',
                        'showHamlet'      => false,
                        'schoolWardParam' => 'ward_id',
                        'schoolUseCode'   => false,
                        'showSchool'      => false,
                        'enableSchool'    => request('enable_school', false),
                        'schoolName'      => 'school_id',
                        'schoolId'        => request('school_id'),
                        'schoolDataUrl'   => route('front.schools.datapicker'),
                        'wrapperClass'    => 'md:col-span-8 grid md:grid-cols-12 gap-3'
                    ])
                </div>

                <!-- Loại hình và Báo cáo -->
                <div class="grid gap-6 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <label for="report_group_id" class="block text-sm font-semibold text-slate-700 mb-2">Loại hình <span class="text-rose-500">*</span></label>
                        <select id="report_group_id" name="report_group_id"
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">— Chọn loại hình —</option>
                            @foreach($reportGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="md:col-span-5">
                        <label for="report_detail_id" class="block text-sm font-semibold text-slate-700 mb-2">Báo cáo <span class="text-rose-500">*</span></label>
                        <select id="report_detail_id" name="report_detail_id"
                                class="w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-slate-100 disabled:cursor-not-allowed"
                                disabled>
                            <option value="">— Vui lòng chọn loại hình trước —</option>
                        </select>
                    </div>
                </div>

                <!-- Nút hành động -->
                <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-100">
                    <button type="button" id="btnViewOnline"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-white font-medium shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>Xem trực tiếp trên web</span>
                    </button>

                    <button type="button" id="btnDownloadExcel"
                            class="inline-flex items-center gap-2 rounded-lg bg-white border border-slate-300 px-4 py-2.5 text-slate-700 font-medium shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 transition-all">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Tải về file Excel (.xlsx)</span>
                    </button>

                    <div id="formValidationError" class="hidden text-sm font-medium text-rose-600 flex items-center gap-1.5 ml-2">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span id="formValidationMsg">Vui lòng điền đầy đủ các thông tin bắt buộc.</span>
                    </div>
                </div>
            </form>
        </div>

        <!-- Web Viewer Container (Xem trực tiếp: Mặc định Toàn màn hình rộng rãi & hỗ trợ xem trong trang) -->
        <div id="reportViewerSection" class="hidden fixed inset-0 z-[100] w-screen h-screen bg-slate-950/80 backdrop-blur-md p-2 sm:p-4 flex flex-col transition-all duration-300">
            <div id="viewerCard" class="w-full h-full bg-white rounded-2xl shadow-2xl border border-slate-700/60 flex flex-col overflow-hidden">
                <!-- Viewer Header & Toolbar -->
                <div class="h-14 bg-slate-900 text-white px-4 sm:px-6 flex items-center justify-between gap-3 border-b border-slate-800 flex-shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="p-2 bg-blue-500/20 text-blue-400 rounded-lg flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 id="viewerReportName" class="font-bold text-white text-sm sm:text-base leading-snug truncate max-w-xs sm:max-w-md lg:max-w-xl">Báo cáo thống kê</h3>
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex-shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                    Trực tiếp
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-slate-400 mt-0.5 truncate">
                                <span id="viewerYearTag" class="text-blue-300 font-medium">Năm học 2026-2027</span>
                                <span>•</span>
                                <span id="viewerGeoTag" class="text-slate-300">Toàn địa bàn</span>
                            </div>
                        </div>
                    </div>

                    <!-- Toolbar Actions -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <!-- Zoom Controls -->
                        <div class="hidden sm:inline-flex items-center bg-slate-800 rounded-lg p-0.5 border border-slate-700 text-xs">
                            <button type="button" id="btnZoomOut" class="px-2.5 py-1 hover:bg-slate-700 rounded text-slate-200 transition font-medium" title="Thu nhỏ (80%)">A-</button>
                            <button type="button" id="btnZoomReset" class="px-2.5 py-1 hover:bg-slate-700 rounded text-slate-200 font-semibold transition" title="Cỡ chuẩn (100%)">100%</button>
                            <button type="button" id="btnZoomIn" class="px-2.5 py-1 hover:bg-slate-700 rounded text-slate-200 transition font-medium" title="Phóng to (120%)">A+</button>
                        </div>

                        <!-- Print Button -->
                        <button type="button" id="btnPrintReport"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-medium border border-slate-700 transition"
                                title="In báo cáo hoặc lưu PDF">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            <span class="hidden md:inline">In báo cáo</span>
                        </button>

                        <!-- Download Quick Button -->
                        <button type="button" id="btnViewerDownload"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium shadow-sm transition"
                                title="Tải file Excel .xlsx">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            <span class="hidden md:inline">Tải Excel</span>
                        </button>

                        <!-- Toggle Fullscreen / Dock on page -->
                        <button type="button" id="btnToggleMode"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-medium border border-slate-700 transition"
                                title="Chuyển đổi Toàn màn hình / Xem trong trang">
                            <svg id="iconModeExpand" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                            </svg>
                            <svg id="iconModeCompress" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 14h6m0 0v6m0-6L3 21m17-7h-6m0 0v6m0-6l7 7M10 10V4m0 6H4m6 0L3 3m10 7h6m0 0V4m0 6l7-7" />
                            </svg>
                            <span id="textModeToggle" class="hidden lg:inline">Thu nhỏ vào trang</span>
                        </button>

                        <!-- Open New Tab Button -->
                        <a id="linkOpenNewTab" href="#" target="_blank"
                           class="inline-flex items-center p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs border border-slate-700 transition"
                           title="Mở trong tab mới">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>

                        <!-- Close Button -->
                        <button type="button" id="btnCloseViewer"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-rose-600/90 hover:bg-rose-600 text-white text-xs font-semibold shadow-sm transition"
                                title="Đóng (hoặc nhấn phím ESC)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            <span>Đóng</span>
                            <span class="text-[10px] bg-rose-700 px-1 py-0.2 rounded text-rose-100 hidden sm:inline">ESC</span>
                        </button>
                    </div>
                </div>

                <!-- Viewer Body / Iframe Area -->
                <div class="relative flex-1 w-full bg-slate-100 flex flex-col overflow-hidden" style="flex: 1 1 0%; min-height: 0; display: flex; flex-direction: column;">
                    <!-- Loading State -->
                    <div id="viewerLoading" class="absolute inset-0 z-20 bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center gap-3">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full border-4 border-slate-200 border-t-blue-600 animate-spin"></div>
                        </div>
                        <div class="text-center">
                            <p class="font-semibold text-slate-800 text-sm">Đang tải và tính toán số liệu biểu mẫu...</p>
                            <p class="text-xs text-slate-500 mt-1">Hệ thống đang kết xuất báo cáo trực tiếp từ dữ liệu PCGD</p>
                        </div>
                    </div>

                    <!-- Report Content Iframe (Explicitly styled width & height to eliminate the 150px bug) -->
                    <iframe id="reportIframe"
                            name="reportIframe"
                            style="width: 100%; height: 100%; min-height: 100%; flex: 1 1 auto; border: 0;"
                            class="w-full h-full border-0 transition-opacity duration-300 opacity-0"
                            src="about:blank"></iframe>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('searchForm');
    const reportGroupSelect = document.getElementById('report_group_id');
    const reportDetailSelect = document.getElementById('report_detail_id');
    const yearSelect = document.getElementById('year');
    
    const btnViewOnline = document.getElementById('btnViewOnline');
    const btnDownloadExcel = document.getElementById('btnDownloadExcel');
    const validationError = document.getElementById('formValidationError');
    const validationMsg = document.getElementById('formValidationMsg');

    const viewerSection = document.getElementById('reportViewerSection');
    const viewerCard = document.getElementById('viewerCard');
    const viewerLoading = document.getElementById('viewerLoading');
    const reportIframe = document.getElementById('reportIframe');
    const viewerReportName = document.getElementById('viewerReportName');
    const viewerYearTag = document.getElementById('viewerYearTag');
    const viewerGeoTag = document.getElementById('viewerGeoTag');
    const linkOpenNewTab = document.getElementById('linkOpenNewTab');
    const btnCloseViewer = document.getElementById('btnCloseViewer');
    const btnPrintReport = document.getElementById('btnPrintReport');
    const btnViewerDownload = document.getElementById('btnViewerDownload');

    const btnToggleMode = document.getElementById('btnToggleMode');
    const iconModeExpand = document.getElementById('iconModeExpand');
    const iconModeCompress = document.getElementById('iconModeCompress');
    const textModeToggle = document.getElementById('textModeToggle');

    const btnZoomIn = document.getElementById('btnZoomIn');
    const btnZoomReset = document.getElementById('btnZoomReset');
    const btnZoomOut = document.getElementById('btnZoomOut');

    let currentZoom = 1;
    let currentPreviewUrl = '';
    let isModal = true;

    // Khi chọn loại hình, load danh sách báo cáo chi tiết
    reportGroupSelect.addEventListener('change', function() {
        const groupId = this.value;
        if (groupId) {
            reportDetailSelect.disabled = false;
            reportDetailSelect.innerHTML = '<option value="">— Đang tải danh sách báo cáo... —</option>';
            
            fetch(`/api/reports/groups/${groupId}/details`)
                .then(response => response.json())
                .then(data => {
                    reportDetailSelect.innerHTML = '<option value="">— Chọn báo cáo —</option>';
                    if (data.success && data.data) {
                        const groupIndex = Array.from(reportGroupSelect.options).findIndex(option => option.value === groupId);
                        const groupNumber = groupIndex;
                        
                        data.data.forEach((detail, index) => {
                            const option = document.createElement('option');
                            option.value = detail.id;
                            option.textContent = `${groupNumber}.${index + 1} ${detail.name}`;
                            reportDetailSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading report details:', error);
                    reportDetailSelect.innerHTML = '<option value="">— Lỗi tải dữ liệu —</option>';
                });
        } else {
            reportDetailSelect.disabled = true;
            reportDetailSelect.innerHTML = '<option value="">— Vui lòng chọn loại hình trước —</option>';
        }
    });

    // Hàm kiểm tra tính hợp lệ của Form
    function validateForm() {
        validationError.classList.add('hidden');

        if (!yearSelect.value) {
            showError('Vui lòng chọn Năm học');
            yearSelect.focus();
            return false;
        }

        // Kiểm tra Tỉnh/Thành phố
        const provInput = form.querySelector('[name="geo[province_id]"]');
        if (provInput && !provInput.value) {
            showError('Vui lòng chọn Tỉnh/Thành phố trong phần Sơ đồ địa bàn');
            provInput.focus();
            return false;
        }

        if (!reportGroupSelect.value) {
            showError('Vui lòng chọn Loại hình báo cáo');
            reportGroupSelect.focus();
            return false;
        }

        if (!reportDetailSelect.value) {
            showError('Vui lòng chọn Biểu mẫu báo cáo cụ thể');
            reportDetailSelect.focus();
            return false;
        }

        return true;
    }

    function showError(msg) {
        validationMsg.textContent = msg;
        validationError.classList.remove('hidden');
    }

    // Lấy query string từ Form
    function buildQueryString(extraParams = {}) {
        const fd = new FormData(form);
        form.querySelectorAll('[name][disabled]').forEach(el => {
            if (el.value != null && String(el.value).length) {
                fd.append(el.name, el.value);
            }
        });

        const params = new URLSearchParams(fd);
        for (const [k, v] of Object.entries(extraParams)) {
            params.set(k, v);
        }
        return params.toString();
    }

    // 1. XỬ LÝ SỰ KIỆN: XEM TRỰC TIẾP TRÊN WEB
    btnViewOnline.addEventListener('click', function() {
        if (!validateForm()) return;

        // Cập nhật thông tin Header Viewer
        const selectedDetailName = reportDetailSelect.options[reportDetailSelect.selectedIndex]?.text || 'Báo cáo thống kê';
        viewerReportName.textContent = selectedDetailName;
        viewerYearTag.textContent = 'Năm học: ' + (yearSelect.value || '');

        // Lấy tên địa bàn nếu có
        let geoText = '';
        const wardSelect = document.querySelector('select[name="geo[ward_id]"]');
        const districtSelect = document.querySelector('select[name="geo[district_id]"]');
        const provinceSelect = document.querySelector('select[name="geo[province_id]"]');

        if (wardSelect && wardSelect.selectedIndex > 0) {
            geoText = wardSelect.options[wardSelect.selectedIndex].text;
        } else if (districtSelect && districtSelect.selectedIndex > 0) {
            geoText = districtSelect.options[districtSelect.selectedIndex].text;
        } else if (provinceSelect && provinceSelect.selectedIndex > 0) {
            geoText = provinceSelect.options[provinceSelect.selectedIndex].text;
        } else {
            geoText = 'Toàn địa bàn';
        }
        viewerGeoTag.textContent = geoText;

        // Áp dụng chế độ xem Toàn màn hình (Full-screen Modal)
        isModal = true;
        applyViewerLayout();

        // Hiển thị khung xem
        viewerSection.classList.remove('hidden');
        viewerLoading.classList.remove('hidden');
        reportIframe.classList.add('opacity-0');

        // Tạo URL Preview
        const qs = buildQueryString({ mode: 'preview' });
        currentPreviewUrl = form.action + '?' + qs;

        linkOpenNewTab.href = currentPreviewUrl;
        reportIframe.src = currentPreviewUrl;
    });

    // Hàm chuyển đổi chế độ xem: Toàn màn hình (Modal) <-> Gắn vào trang (Embedded)
    function applyViewerLayout() {
        if (!viewerSection || !viewerCard) return;

        if (isModal) {
            // Chế độ Toàn màn hình: Chiếm trọn 100vw x 100vh, giao diện sang trọng, không bị cắt xén
            viewerSection.style.position = 'fixed';
            viewerSection.style.inset = '0';
            viewerSection.style.zIndex = '99999';
            viewerSection.style.width = '100vw';
            viewerSection.style.height = '100vh';
            viewerSection.style.padding = '8px';
            viewerSection.style.backgroundColor = 'rgba(15, 23, 42, 0.88)';
            viewerSection.style.backdropFilter = 'blur(10px)';
            viewerSection.style.display = 'flex';
            viewerSection.style.flexDirection = 'column';
            viewerSection.style.marginTop = '0';

            viewerCard.style.width = '100%';
            viewerCard.style.height = '100%';
            viewerCard.style.borderRadius = '14px';
            viewerCard.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.6)';
            viewerCard.style.border = '1px solid rgba(148, 163, 184, 0.2)';

            if (reportIframe) {
                reportIframe.style.width = '100%';
                reportIframe.style.height = '100%';
                reportIframe.style.minHeight = '100%';
            }

            if (iconModeExpand) iconModeExpand.classList.add('hidden');
            if (iconModeCompress) iconModeCompress.classList.remove('hidden');
            if (textModeToggle) textModeToggle.textContent = 'Thu nhỏ vào trang';

            document.body.style.overflow = 'hidden';
        } else {
            // Chế độ Gắn vào trang (Embedded): Đặt bên dưới form với chiều cao rộng rãi (85vh, min 750px)
            viewerSection.style.position = 'static';
            viewerSection.style.inset = 'auto';
            viewerSection.style.zIndex = 'auto';
            viewerSection.style.width = '100%';
            viewerSection.style.height = 'auto';
            viewerSection.style.padding = '0';
            viewerSection.style.backgroundColor = 'transparent';
            viewerSection.style.backdropFilter = 'none';
            viewerSection.style.display = 'block';
            viewerSection.style.marginTop = '2rem';

            viewerCard.style.width = '100%';
            viewerCard.style.height = '85vh';
            viewerCard.style.minHeight = '750px';
            viewerCard.style.borderRadius = '16px';
            viewerCard.style.boxShadow = '0 10px 25px -5px rgba(0, 0, 0, 0.1)';
            viewerCard.style.border = '1px solid #cbd5e1';

            if (reportIframe) {
                reportIframe.style.width = '100%';
                reportIframe.style.height = '100%';
                reportIframe.style.minHeight = '650px';
            }

            if (iconModeExpand) iconModeExpand.classList.remove('hidden');
            if (iconModeCompress) iconModeCompress.classList.add('hidden');
            if (textModeToggle) textModeToggle.textContent = 'Toàn màn hình';

            document.body.style.overflow = '';
            setTimeout(() => {
                viewerSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 60);
        }
    }

    // Nút chuyển đổi chế độ xem
    if (btnToggleMode) {
        btnToggleMode.addEventListener('click', function() {
            isModal = !isModal;
            applyViewerLayout();
        });
    }

    // Khi iframe load xong
    reportIframe.addEventListener('load', function() {
        if (reportIframe.src && reportIframe.src !== 'about:blank') {
            viewerLoading.classList.add('hidden');
            reportIframe.classList.remove('opacity-0');
            currentZoom = 1;
            applyZoom(currentZoom);
        }
    });

    // 2. XỬ LÝ SỰ KIỆN: TẢI VỀ FILE EXCEL (.XLSX)
    function triggerDownload() {
        if (!validateForm()) return;
        const qs = buildQueryString();
        window.location.href = form.action + '?' + qs;
    }

    btnDownloadExcel.addEventListener('click', triggerDownload);
    btnViewerDownload.addEventListener('click', triggerDownload);

    // 3. THANH CÔNG CỤ VIEWER
    // Đóng Viewer (Hỗ trợ nút Đóng & Phím ESC)
    function closeViewer() {
        viewerSection.classList.add('hidden');
        viewerSection.style.display = 'none';
        reportIframe.src = 'about:blank';
        document.body.style.overflow = '';
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }
    }

    btnCloseViewer.addEventListener('click', closeViewer);

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !viewerSection.classList.contains('hidden') && viewerSection.style.display !== 'none') {
            closeViewer();
        }
    });

    // In báo cáo
    btnPrintReport.addEventListener('click', function() {
        try {
            if (reportIframe.contentWindow) {
                reportIframe.contentWindow.focus();
                reportIframe.contentWindow.postMessage({ type: 'PRINT_REPORT' }, '*');
                setTimeout(() => {
                    reportIframe.contentWindow.print();
                }, 100);
            }
        } catch (e) {
            console.error('Print error:', e);
            window.open(currentPreviewUrl, '_blank');
        }
    });

    // Thu phóng (Zoom)
    function applyZoom(zoomLevel) {
        currentZoom = zoomLevel;
        btnZoomReset.textContent = Math.round(currentZoom * 100) + '%';
        try {
            if (reportIframe.contentWindow) {
                reportIframe.contentWindow.postMessage({ type: 'ZOOM_REPORT', value: currentZoom }, '*');
                if (reportIframe.contentDocument && reportIframe.contentDocument.body) {
                    reportIframe.contentDocument.body.style.zoom = currentZoom;
                }
            }
        } catch (e) {}
    }

    btnZoomIn.addEventListener('click', () => {
        if (currentZoom < 1.6) applyZoom(Number((currentZoom + 0.1).toFixed(1)));
    });

    btnZoomReset.addEventListener('click', () => {
        applyZoom(1.0);
    });

    btnZoomOut.addEventListener('click', () => {
        if (currentZoom > 0.6) applyZoom(Number((currentZoom - 0.1).toFixed(1)));
    });
});

</script>
@endpush
