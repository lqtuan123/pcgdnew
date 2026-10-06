@extends('layouts.front')
@section('title', 'Biên bản kiểm tra kết quả PCGD-XMC')
@section('description', 'Tạo biên bản tự kiểm tra kết quả phổ cập giáo dục, xóa mù chữ')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Biên bản kiểm tra kết quả PCGD-XMC</h1>
            <p class="mt-2 text-gray-600">Tạo biên bản tự kiểm tra kết quả phổ cập giáo dục, xóa mù chữ</p>
        </div>

        <!-- Geo Filter -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Khu vực báo cáo</h2>
            @include('partials.front.geo-filter', [
                'scheme' => 'new',
                'schemeMode' => 'new',
                'showHamlet' => true,
                'schoolWardParam' => 'ward_id',
                'schoolUseCode' => false,
                'geo' => $geo,
                'namePrefix' => 'geo',
                'provinceId' => $geo['province_id'],
                'wardId' => $geo['ward_id'],
                'villageId' => $geo['village_id'],
                'lockProvince' => !empty($geo['province_id']), // Khóa tỉnh nếu user có tỉnh
                'lockWard' => !empty($geo['ward_id']), // Khóa xã nếu user có xã
                'disabled' => false, // Cho phép thay đổi
            ])
        </div>

        <!-- Năm học -->
        <div class="bg-white rounded-lg shadow-sm border p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Năm học báo cáo</h2>
            <div class="grid gap-6 md:grid-cols-12">
                <div class="md:col-span-3">
                    <label for="academic_year" class="block text-sm font-medium text-gray-700 mb-2">Năm học</label>
                    <select id="academic_year" name="academic_year"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">— Chọn năm học —</option>
                        @php
                            $base = \App\Models\SurveyForm::currentAcademicYear();
                            [$y1,$y2] = explode('-', $base);
                            // Hiển thị 5 năm: 2 trước, năm hiện tại, 2 sau
                            $years = [($y1-2).'-'.($y1-1), ($y1-1).'-'.$y1, $base, $y2.'-'.($y2+1), ($y2+1).'-'.($y2+2)];
                        @endphp
                        @foreach($years as $yy)
                            <option value="{{ $yy }}" @selected(request('academic_year',$base)===$yy)>{{ $yy }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="bg-white rounded-lg shadow-sm border">
            <!-- Tabs -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                    <button type="button" 
                            class="tab-button active py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600"
                            data-tab="noi-dung-tu-kiem-tra">
                        Nội dung tự kiểm tra
                    </button>
                    <button type="button" 
                            class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300"
                            data-tab="de-xuat-kien-nghi">
                        Đề xuất - Kiến nghị
                    </button>
                    <button type="button" 
                            class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300"
                            data-tab="dieu-kien-dam-bao">
                        Điều kiện đảm bảo
                    </button>
                </nav>
            </div>

            <!-- Form -->
            <form id="pcgdXmcReportForm" method="POST" action="{{ route('front.pcgd-xmc-report.export') }}" class="p-6">
                @csrf
                
                <!-- Hidden field để submit năm học -->
                <input type="hidden" id="academic_year_hidden" name="academic_year" value="{{ request('academic_year', \App\Models\SurveyForm::currentAcademicYear()) }}">
                
                <!-- Hidden fields để submit geo data -->
                <input type="hidden" id="geo_province_id" name="geo[province_id]" value="{{ $geo['province_id'] }}">
                <input type="hidden" id="geo_ward_id" name="geo[ward_id]" value="{{ $geo['ward_id'] }}">
                <input type="hidden" id="geo_village_id" name="geo[village_id]" value="{{ $geo['village_id'] }}">
                
                <!-- Tab Content: Nội dung tự kiểm tra -->
                <div id="noi-dung-tu-kiem-tra-content" class="tab-content">
                    <div class="space-y-6">
                        <div>
                            <label for="uu_diem" class="block text-sm font-medium text-gray-700 mb-2">
                                Ưu điểm
                            </label>
                            <textarea id="uu_diem" 
                                    name="uu_diem" 
                                    rows="8"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Nhập nội dung ưu điểm...">{{ $draftData['uu_diem'] ?? '' }}</textarea>
                        </div>

                        <div>
                            <label for="nhuoc_diem" class="block text-sm font-medium text-gray-700 mb-2">
                                Hạn chế
                            </label>
                            <textarea id="nhuoc_diem" 
                                    name="nhuoc_diem" 
                                    rows="8"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Nhập nội dung hạn chế...">{{ $draftData['nhuoc_diem'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Đề xuất - Kiến nghị -->
                <div id="de-xuat-kien-nghi-content" class="tab-content hidden">
                    <div class="space-y-6">
                        <div>
                            <label for="de_xuat_kien_nghi" class="block text-sm font-medium text-gray-700 mb-2">
                                Đề xuất - Kiến nghị
                            </label>
                            <textarea id="de_xuat_kien_nghi" 
                                    name="de_xuat_kien_nghi" 
                                    rows="12"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Nhập đề xuất và kiến nghị...">{{ $draftData['de_xuat_kien_nghi'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Tab Content: Điều kiện đảm bảo -->
                <div id="dieu-kien-dam-bao-content" class="tab-content hidden">
                    <div class="space-y-6">
                        <!-- Số người huy động học xóa mù chữ -->
                        <div>
                            <label for="so_nguoi_huy_dong_hoc_xoa_mu_chu" class="block text-sm font-medium text-gray-700 mb-2">
                                Số người huy động học xóa mù chữ trong năm
                            </label>
                            <input type="text" 
                                    id="so_nguoi_huy_dong_hoc_xoa_mu_chu" 
                                    name="so_nguoi_huy_dong_hoc_xoa_mu_chu" 
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Nhập số người huy động..."
                                    value="{{ $draftData['so_nguoi_huy_dong_hoc_xoa_mu_chu'] ?? '' }}">
                        </div>

                        <!-- Đơn vị/xã không đạt chuẩn xóa mù chữ -->
                        <div>
                            <label for="don_vi_khong_dat_chuan_xoa_mu_chu" class="block text-sm font-medium text-gray-700 mb-2">
                                Số đơn vị (hoặc xã) không đủ điều kiện đạt chuẩn về xóa mù chữ mức độ 1/mức độ 2
                            </label>
                            <textarea id="don_vi_khong_dat_chuan_xoa_mu_chu" 
                                    name="don_vi_khong_dat_chuan_xoa_mu_chu" 
                                    rows="3"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Nhập danh sách đơn vị/xã không đạt chuẩn...">{{ $draftData['don_vi_khong_dat_chuan_xoa_mu_chu'] ?? '' }}</textarea>
                        </div>

                        <!-- Cơ sở vật chất, thiết bị dạy học -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">a) Cơ sở vật chất, thiết bị dạy học</h3>
                            <div class="space-y-4">
                                <div>
                                    <label for="co_so_vat_chat_mam_non" class="block text-sm font-medium text-gray-700 mb-2">
                                        Mầm non
                                    </label>
                                    <textarea id="co_so_vat_chat_mam_non" 
                                            name="co_so_vat_chat_mam_non" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin cơ sở vật chất mầm non...">{{ $draftData['co_so_vat_chat_mam_non'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label for="co_so_vat_chat_tieu_hoc" class="block text-sm font-medium text-gray-700 mb-2">
                                        Tiểu học
                                    </label>
                                    <textarea id="co_so_vat_chat_tieu_hoc" 
                                            name="co_so_vat_chat_tieu_hoc" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin cơ sở vật chất tiểu học...">{{ $draftData['co_so_vat_chat_tieu_hoc'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label for="co_so_vat_chat_trung_hoc_co_so" class="block text-sm font-medium text-gray-700 mb-2">
                                        Trung học cơ sở
                                    </label>
                                    <textarea id="co_so_vat_chat_trung_hoc_co_so" 
                                            name="co_so_vat_chat_trung_hoc_co_so" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin cơ sở vật chất trung học cơ sở...">{{ $draftData['co_so_vat_chat_trung_hoc_co_so'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label for="co_so_vat_chat_trung_tam_gdnn_gdtx" class="block text-sm font-medium text-gray-700 mb-2">
                                        Trung tâm giáo dục nghề nghiệp - giáo dục thường xuyên
                                    </label>
                                    <textarea id="co_so_vat_chat_trung_tam_gdnn_gdtx" 
                                            name="co_so_vat_chat_trung_tam_gdnn_gdtx" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin cơ sở vật chất trung tâm GDNN-GDTX...">{{ $draftData['co_so_vat_chat_trung_tam_gdnn_gdtx'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label for="co_so_vat_chat_trung_tam_hoc_tap_cong_dong" class="block text-sm font-medium text-gray-700 mb-2">
                                        Trung tâm học tập cộng đồng
                                    </label>
                                    <textarea id="co_so_vat_chat_trung_tam_hoc_tap_cong_dong" 
                                            name="co_so_vat_chat_trung_tam_hoc_tap_cong_dong" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin cơ sở vật chất trung tâm học tập cộng đồng...">{{ $draftData['co_so_vat_chat_trung_tam_hoc_tap_cong_dong'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Kinh phí -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">b) Kinh phí</h3>
                            <div class="space-y-4">
                                <div>
                                    <label for="kinh_phi_pho_cap_giao_duc" class="block text-sm font-medium text-gray-700 mb-2">
                                        Kinh phí phổ cập giáo dục
                                    </label>
                                    <input type="text" 
                                            id="kinh_phi_pho_cap_giao_duc" 
                                            name="kinh_phi_pho_cap_giao_duc" 
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập kinh phí phổ cập giáo dục..."
                                            value="{{ $draftData['kinh_phi_pho_cap_giao_duc'] ?? '' }}">
                                </div>

                                <div>
                                    <label for="kinh_phi_xoa_mu_chu" class="block text-sm font-medium text-gray-700 mb-2">
                                        Kinh phí xóa mù chữ
                                    </label>
                                    <input type="text" 
                                            id="kinh_phi_xoa_mu_chu" 
                                            name="kinh_phi_xoa_mu_chu" 
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập kinh phí xóa mù chữ..."
                                            value="{{ $draftData['kinh_phi_xoa_mu_chu'] ?? '' }}">
                                </div>
                            </div>
                        </div>

                        <!-- Người tham gia dạy học, theo dõi -->
                        <div class="border-t border-gray-200 pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">c) Người tham gia dạy học, theo dõi phổ cập giáo dục, xóa mù chữ</h3>
                            <div class="space-y-4">
                                <div>
                                    <label for="nguoi_tham_gia_mam_non" class="block text-sm font-medium text-gray-700 mb-2">
                                        Mầm non
                                    </label>
                                    <textarea id="nguoi_tham_gia_mam_non" 
                                            name="nguoi_tham_gia_mam_non" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin người tham gia mầm non...">{{ $draftData['nguoi_tham_gia_mam_non'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label for="nguoi_tham_gia_tieu_hoc" class="block text-sm font-medium text-gray-700 mb-2">
                                        Tiểu học
                                    </label>
                                    <textarea id="nguoi_tham_gia_tieu_hoc" 
                                            name="nguoi_tham_gia_tieu_hoc" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin người tham gia tiểu học...">{{ $draftData['nguoi_tham_gia_tieu_hoc'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label for="nguoi_tham_gia_trung_hoc_co_so" class="block text-sm font-medium text-gray-700 mb-2">
                                        Trung học cơ sở
                                    </label>
                                    <textarea id="nguoi_tham_gia_trung_hoc_co_so" 
                                            name="nguoi_tham_gia_trung_hoc_co_so" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin người tham gia trung học cơ sở...">{{ $draftData['nguoi_tham_gia_trung_hoc_co_so'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label for="nguoi_tham_gia_trung_tam_gdnn_gdtx" class="block text-sm font-medium text-gray-700 mb-2">
                                        Trung tâm giáo dục nghề nghiệp - giáo dục thường xuyên
                                    </label>
                                    <textarea id="nguoi_tham_gia_trung_tam_gdnn_gdtx" 
                                            name="nguoi_tham_gia_trung_tam_gdnn_gdtx" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin người tham gia trung tâm GDNN-GDTX...">{{ $draftData['nguoi_tham_gia_trung_tam_gdnn_gdtx'] ?? '' }}</textarea>
                                </div>

                                <div>
                                    <label for="nguoi_tham_gia_trung_tam_hoc_tap_cong_dong" class="block text-sm font-medium text-gray-700 mb-2">
                                        Trung tâm học tập cộng đồng
                                    </label>
                                    <textarea id="nguoi_tham_gia_trung_tam_hoc_tap_cong_dong" 
                                            name="nguoi_tham_gia_trung_tam_hoc_tap_cong_dong" 
                                            rows="3"
                                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            placeholder="Nhập thông tin người tham gia trung tâm học tập cộng đồng...">{{ $draftData['nguoi_tham_gia_trung_tam_hoc_tap_cong_dong'] ?? '' }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                    <button type="button" 
                            id="saveDraftBtn"
                            class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        Lưu thông tin
                    </button>
                    <button type="submit" 
                            id="exportBtn"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                        Xuất biên bản
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Đồng bộ năm học từ dropdown vào hidden field
    const academicYearSelect = document.getElementById('academic_year');
    const academicYearHidden = document.getElementById('academic_year_hidden');
    
    if (academicYearSelect && academicYearHidden) {
        // Set giá trị ban đầu
        academicYearHidden.value = academicYearSelect.value;
        
        // Đồng bộ khi thay đổi
        academicYearSelect.addEventListener('change', function() {
            academicYearHidden.value = this.value;
        });
    }

    // Đồng bộ geo data từ geo-filter vào hidden fields
    const geoProvinceHidden = document.getElementById('geo_province_id');
    const geoWardHidden = document.getElementById('geo_ward_id');
    const geoVillageHidden = document.getElementById('geo_village_id');
    
    // Function để đồng bộ geo data
    function syncGeoData() {
        // Tìm geo-filter component bằng nhiều cách
        let geoFilter = document.querySelector('[x-data*="geoFilter"]');
        if (!geoFilter) {
            geoFilter = document.querySelector('[x-data*="geo-filter"]');
        }
        if (!geoFilter) {
            geoFilter = document.querySelector('.geo-filter');
        }
        if (!geoFilter) {
            // Tìm trong tất cả div có x-data chứa geoFilter
            const allDivs = document.querySelectorAll('div[x-data]');
            for (let div of allDivs) {
                if (div.getAttribute('x-data').includes('geoFilter')) {
                    geoFilter = div;
                    break;
                }
            }
        }
        
        console.log('Geo filter element:', geoFilter);
        
        if (geoFilter) {
            // Cách 1: Truy cập qua Alpine.js
            if (window.Alpine && geoFilter._x_dataStack && geoFilter._x_dataStack[0]) {
                const geoData = geoFilter._x_dataStack[0];
                if (geoProvinceHidden) geoProvinceHidden.value = geoData.selProvNew || '';
                if (geoWardHidden) geoWardHidden.value = geoData.selWardNew || '';
                if (geoVillageHidden) geoVillageHidden.value = geoData.selHamletNew || '';
                console.log('Synced geo data from Alpine:', {
                    province: geoData.selProvNew,
                    ward: geoData.selWardNew,
                    village: geoData.selHamletNew
                });
            }
            // Cách 2: Truy cập qua hidden fields của geo-filter
            else {
                const geoProvInput = geoFilter.querySelector('input[name*="geo[province_id]"]');
                const geoWardInput = geoFilter.querySelector('input[name*="geo[ward_id]"]');
                const geoVillageInput = geoFilter.querySelector('input[name*="geo[village_id]"]');
                
                if (geoProvInput && geoProvinceHidden) geoProvinceHidden.value = geoProvInput.value || '';
                if (geoWardInput && geoWardHidden) geoWardHidden.value = geoWardInput.value || '';
                if (geoVillageInput && geoVillageHidden) geoVillageHidden.value = geoVillageInput.value || '';
                
                console.log('Synced geo data from inputs:', {
                    province: geoProvInput?.value,
                    ward: geoWardInput?.value,
                    village: geoVillageInput?.value
                });
            }
        } else {
            console.log('Geo filter not found');
        }
    }
    
    // Đồng bộ ban đầu và khi có thay đổi
    setTimeout(syncGeoData, 1000); // Đợi Alpine.js khởi tạo
    
    // Đồng bộ khi form submit
    document.getElementById('pcgdXmcReportForm').addEventListener('submit', function() {
        syncGeoData();
    });
    
    // Đồng bộ khi dropdown thay đổi
    document.addEventListener('change', function(e) {
        if (e.target.name && e.target.name.includes('ward_id')) {
            console.log('Ward dropdown changed:', e.target.value);
            
            // Đồng bộ trực tiếp từ dropdown
            if (geoWardHidden) {
                geoWardHidden.value = e.target.value || '';
                console.log('Direct sync ward_id:', geoWardHidden.value);
            }
            
            // Không gọi syncGeoData() nữa vì đã đồng bộ trực tiếp
            // setTimeout(syncGeoData, 100); // Đợi Alpine.js cập nhật
        }
        
        // Đồng bộ khi province dropdown thay đổi
        if (e.target.name && e.target.name.includes('province_id')) {
            console.log('Province dropdown changed:', e.target.value);
            
            // Đồng bộ trực tiếp từ dropdown
            if (geoProvinceHidden) {
                geoProvinceHidden.value = e.target.value || '';
                console.log('Direct sync province_id:', geoProvinceHidden.value);
            }
        }
    });

    // Tab switching
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all buttons
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Add active class to clicked button
            this.classList.add('active', 'border-blue-500', 'text-blue-600');
            this.classList.remove('border-transparent', 'text-gray-500');
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show target tab content
            const targetContent = document.getElementById(targetTab + '-content');
            if (targetContent) {
                targetContent.classList.remove('hidden');
            }
        });
    });

    // Form submission
    document.getElementById('pcgdXmcReportForm').addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('exportBtn');
        const originalText = submitBtn.textContent;
        
        // Show loading state
        submitBtn.textContent = 'Đang xuất...';
        submitBtn.disabled = true;
        
        // Không preventDefault để form submit bình thường
        // Form sẽ redirect đến route download sau khi xử lý xong
        
        // Reset button sau 5 giây nếu không có redirect (fallback)
        setTimeout(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }, 5000);
    });

    // Save draft functionality
    const saveDraftBtn = document.getElementById('saveDraftBtn');
    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', function() {
            const form = document.getElementById('pcgdXmcReportForm');
            const formData = new FormData(form);
            
            // Disable button and show loading
            const originalText = saveDraftBtn.textContent;
            saveDraftBtn.textContent = 'Đang lưu...';
            saveDraftBtn.disabled = true;
            
            // Send AJAX request
            fetch('{{ route("front.pcgd-xmc-report.save-draft") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Đã lưu thông tin thành công!');
                } else {
                    alert('Lỗi: ' + (data.message || 'Không thể lưu thông tin'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi lưu thông tin');
            })
            .finally(() => {
                // Reset button
                saveDraftBtn.textContent = originalText;
                saveDraftBtn.disabled = false;
            });
        });
    }
});
</script>
@endpush

@endsection
