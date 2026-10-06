 @extends('layouts.front')

@section('title', 'Thực hiện lên lớp')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Thực hiện lên lớp</h1>
        <p class="text-gray-600">Thực hiện lên lớp cho học sinh trong xã/phường/thôn đã chọn</p>
        <div class="mt-3 flex items-center gap-3">
            <span id="lockStatusText" class="text-sm px-2 py-1 rounded border border-gray-300 text-gray-700">Đang kiểm tra trạng thái khóa...</span>
            <button type="button" id="lockToggleBtn" class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded border border-gray-300" disabled>Bấm khóa</button>
        </div>
    </div>

    <!-- Form -->
    <form id="levelTransferForm" class="bg-white rounded-lg shadow-md p-6">
        @csrf
        
        <!-- Năm học -->
        <div class="mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Chọn năm học hiện tại</label>
                <select name="academic_year_from" id="academic_year_from" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                        required>
                    <option value="">-- Chọn năm học --</option>
                    @foreach($availableAcademicYears as $year)
                        <option value="{{ $year }}" @selected($year == $currentAcademicYear)>{{ $year }}</option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-500 mt-1">Hệ thống sẽ tự động tính năm học tiếp theo khi thực hiện lên lớp</p>
            </div>
        </div>

        <!-- Địa chỉ hành chính -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Địa chỉ hành chính</h3>
            <div class="md:col-span-12">
                @include('partials.front.geo-filter', [
                    'scheme'      => 'new',
                    'schemeMode'  => 'new',   // hoặc 'old' / 'new'
                    'showHamlet'  => true,
                    'schoolWardParam' => 'ward_id',
                    'schoolUseCode'   => false,
                    // (tùy chọn) trường:
                    'showSchool'  => true,
                    'enableSchool'=> request('enable_school', true),
                    'schoolName'  => 'school_id',
                    'schoolId'    => request('school_id'),
                    'schoolDataUrl' => route('front.schools.datapicker'),
                    'wrapperClass'=> 'md:col-span-8 grid md:grid-cols-12 gap-3'
                ])
            </div>
        </div>

        <!-- Tùy chọn -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Tùy chọn</h3>
            <div class="space-y-3">
                <label class="flex items-center">
                    <input type="checkbox" name="keep_class_name" id="keep_class_name" class="mr-3">
                    <span class="text-sm text-gray-700">Giữ lại tên lớp (6A1 → 7A1)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="keep_transfer_objects" id="keep_transfer_objects" class="mr-3">
                    <span class="text-sm text-gray-700">Giữ đối tượng chuyển đi</span>
                </label>
            </div>
        </div>

        

        <!-- Thông tin lên cấp -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Cấu hình trường cho lên cấp</h3>
            
            <div class="border border-gray-200 rounded-lg p-4 mb-4">
                <h4 class="font-medium text-gray-900 mb-3">Chọn trường cho các khối tốt nghiệp</h4>
                <p class="text-sm text-gray-600 mb-4">
                    Hệ thống sẽ tự động lên cấp cho học sinh ở các khối tốt nghiệp: 5-6 tuổi, lớp 5, lớp 9, lớp 12.
                    Bạn cần chọn trường cấp học tiếp theo cho từng loại.
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Mầm non lên Tiểu học -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h5 class="font-medium text-gray-800 mb-2">Mầm non → Tiểu học</h5>
                        <p class="text-sm text-gray-600 mb-3">Trường tiểu học cho học sinh 5-6 tuổi</p>
                        <select name="next_school_mapping[preschool][school_id]" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                                data-level="preschool">
                            <option value="">Chọn trường tiểu học...</option>
                        </select>
                        <input type="hidden" name="next_school_mapping[preschool][level]" value="preschool">
                    </div>
                    
                    <!-- Tiểu học lên THCS -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h5 class="font-medium text-gray-800 mb-2">Tiểu học → THCS</h5>
                        <p class="text-sm text-gray-600 mb-3">Trường THCS cho học sinh lớp 5</p>
                        <select name="next_school_mapping[primary][school_id]" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                data-level="primary">
                            <option value="">Chọn trường THCS...</option>
                        </select>
                        <input type="hidden" name="next_school_mapping[primary][level]" value="primary">
                    </div>
                    
                    <!-- THCS lên THPT -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h5 class="font-medium text-gray-800 mb-2">THCS → THPT</h5>
                        <p class="text-sm text-gray-600 mb-3">Trường THPT cho học sinh lớp 9</p>
                        <select name="next_school_mapping[junior][school_id]" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                                data-level="junior">
                            <option value="">Chọn trường THPT...</option>
                        </select>
                        <input type="hidden" name="next_school_mapping[junior][level]" value="junior">
                    </div>
                    
                    <!-- THPT tốt nghiệp -->
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h5 class="font-medium text-gray-800 mb-2">THPT → Tốt nghiệp</h5>
                        <p class="text-sm text-gray-600 mb-3">Học sinh lớp 12 sẽ tốt nghiệp</p>
                        
                        <input type="hidden" name="next_school_mapping[high][school_id]" value="">
                        <input type="hidden" name="next_school_mapping[high][level]" value="high">
                    </div>
                </div>
            </div>
        </div>

        <!-- Nút thực hiện -->
        <div class="flex justify-end">
            <button type="submit" id="executeBtn" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                Thực hiện lên lớp và lên cấp
            </button>
        </div>
    </form>

    <!-- Loading overlay -->
    <div id="loadingOverlay" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 text-center max-w-md mx-4">
            <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto mb-6"></div>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">🔄 Đang thực hiện lên lớp...</h3>
            <p class="text-gray-600 mb-4">Vui lòng chờ trong giây lát</p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p class="text-sm text-blue-800">
                    📚 Hệ thống đang xử lý tất cả học sinh trong xã/phường/thôn đã chọn<br>
                    ⏱️ Quá trình này có thể mất vài phút tùy thuộc vào số lượng học sinh
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Ẩn các trường level transfer ban đầu
    document.querySelectorAll('[id$="_school_selection"]').forEach(el => el.classList.add('hidden'));
    
    // Thêm hidden input cho level
    addHiddenLevelInputs();
    
    // Xử lý thay đổi địa bàn từ geo-filter
    setupGeoListeners();
    
    // Xử lý thay đổi năm học hiện tại để tự động tính năm học tiếp theo
    setupAcademicYearHandler();
    
    // Load danh sách trường cho các cấp học tiếp theo
    loadNextLevelSchools();
    
    // Xử lý submit form
    document.getElementById('levelTransferForm').addEventListener('submit', handleSubmit);

        // Sự kiện khóa/mở khóa
        document.getElementById('lockToggleBtn').addEventListener('click', handleLockToggle);

        // Kiểm tra trạng thái khóa ban đầu
        refreshLockStatus();
});

function addHiddenLevelInputs() {
    // Không cần thêm level inputs vì giờ xử lý tất cả các cấp
    // Chỉ cần thêm hidden input để đánh dấu là lên lớp tất cả
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'process_all_levels';
    input.value = '1';
    document.getElementById('levelTransferForm').appendChild(input);
}

function loadNextLevelSchools() {
    // Lấy thông tin địa bàn từ geo-filter
    const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
    if (!geoFilterElement || !geoFilterElement._x_dataStack) {
        console.log('Chưa có thông tin địa bàn, sẽ load sau khi chọn địa bàn');
        return;
    }
    
    const geoFilterData = geoFilterElement._x_dataStack[0];
    const scheme = geoFilterData.scheme || 'new';
    
    let provinceId, districtId, wardId;
    
    if (scheme === 'old') {
        provinceId = geoFilterData.selProvOld || 
                     document.querySelector('input[name="geo[province_id]"]')?.value;
        districtId = geoFilterData.selDistOld || 
                     document.querySelector('input[name="geo[district_id]"]')?.value;
        wardId = geoFilterData.selWardOld || 
                 document.querySelector('input[name="geo[ward_id]"]')?.value;
    } else {
        provinceId = geoFilterData.selProvNew || 
                     document.querySelector('input[name="geo[province_id]"]')?.value;
        districtId = '';
        wardId = geoFilterData.selWardNew || 
                 document.querySelector('input[name="geo[ward_id]"]')?.value;
    }
    
    if (!provinceId) {
        console.log('Chưa chọn tỉnh, sẽ load sau khi chọn địa bàn');
        return;
    }
    
    // Load danh sách trường cho từng cấp học tiếp theo
    const levels = ['preschool', 'primary', 'junior'];
    
    levels.forEach(level => {
        loadSchoolsForLevel(level, provinceId, districtId, wardId, scheme);
    });
}

function loadSchoolsForLevel(level, provinceId, districtId, wardId, scheme) {
    const selectElement = document.querySelector(`select[data-level="${level}"]`);
    if (!selectElement) return;
    
    // Hiển thị loading
    selectElement.innerHTML = '<option value="">Đang tải...</option>';
    selectElement.disabled = true;
    
    const params = new URLSearchParams({
        level: level,
        province_id: provinceId,
        scheme: scheme,
        scope: 'geo'
    });
    
    if (districtId) params.append('district_id', districtId);
    if (wardId) params.append('ward_id', wardId);
    
    fetch(`/thuc-hien-len-lop/get-next-level-schools?${params}`)
        .then(response => response.json())
        .then(data => {
            selectElement.innerHTML = '<option value="">Chọn trường...</option>';
            
            if (data && data.length > 0) {
                data.forEach(school => {
                    const option = document.createElement('option');
                    option.value = school.id;
                    option.textContent = school.name;
                    selectElement.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'Không có trường phù hợp';
                selectElement.appendChild(option);
            }
            
            selectElement.disabled = false;
        })
        .catch(error => {
            console.error('Lỗi khi tải danh sách trường:', error);
            selectElement.innerHTML = '<option value="">Lỗi khi tải dữ liệu</option>';
            selectElement.disabled = false;
        });
}

function setupAcademicYearHandler() {
    const academicYearSelect = document.getElementById('academic_year_from');
    
    academicYearSelect.addEventListener('change', function() {
        const selectedYear = this.value;
        if (selectedYear) {
            // Tự động tính năm học tiếp theo
            const nextYear = calculateNextAcademicYear(selectedYear);
            
            // Cập nhật thông tin hiển thị (không cần input field nữa)
            updateAcademicYearInfo(selectedYear, nextYear);
        }
        // Mỗi khi đổi năm học, cập nhật trạng thái khóa
        refreshLockStatus();
    });
}

function calculateNextAcademicYear(currentYear) {
    // Parse năm học hiện tại (ví dụ: "2024-2025")
    const parts = currentYear.split('-');
    if (parts.length === 2) {
        const currentStartYear = parseInt(parts[0]);
        const nextStartYear = currentStartYear + 1;
        return nextStartYear + '-' + (nextStartYear + 1);
    }
    return '';
}

function updateAcademicYearInfo(currentYear, nextYear) {
    // Có thể thêm thông tin hiển thị năm học tiếp theo nếu cần
    console.log('Năm học hiện tại:', currentYear);
    console.log('Năm học tiếp theo:', nextYear);
}

function toggleSchoolSelection(level) {
    const checkbox = document.getElementById(level + '_specific');
    const selection = document.getElementById(level + '_school_selection');
    const allSchools = document.getElementById(level + '_all_schools');
    
    if (checkbox.checked) {
        selection.classList.remove('hidden');
        allSchools.classList.add('hidden');
        loadSchools(level, 'old');
    } else {
        selection.classList.add('hidden');
        allSchools.classList.remove('hidden');
    }
}

function setupGeoListeners() {
    // Lắng nghe thay đổi geo-filter
    const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
    if (geoFilterElement && geoFilterElement._x_dataStack) {
        const geoFilterData = geoFilterElement._x_dataStack[0];
        
        // Lắng nghe thay đổi scheme
        geoFilterData.$watch('scheme', (newScheme) => {
            updateGeoInfo(); // Load schools when scheme changes
        });
        
        // Lắng nghe thay đổi tỉnh/xã
        geoFilterData.$watch('selProvNew', () => {
            updateGeoInfo(); // Load schools when geo changes
        });
        geoFilterData.$watch('selWardNew', () => {
            updateGeoInfo(); // Load schools when geo changes
        });
        geoFilterData.$watch('selHamletNew', () => {
            updateGeoInfo(); // Load schools when geo changes
        });
    }
    
    // Lắng nghe thay đổi scope radio buttons
    document.querySelectorAll('input[name*="_scope"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateGeoInfo(); // Load schools when scope changes
        });
    });
}

function toggleScheme(scheme) {
    // Geo-filter sẽ tự động xử lý scheme switching
    updateGeoInfo();
}

// Mặc định hiển thị scheme mới - không cần thiết nữa vì geo-filter tự động xử lý

// Các hàm load geo units đã được thay thế bởi geo-filter component

function updateGeoInfo() {
    // Lấy thông tin địa bàn từ geo-filter
    const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
    if (!geoFilterElement || !geoFilterElement._x_dataStack) return;
    
    const geoFilterData = geoFilterElement._x_dataStack[0];
    const scheme = geoFilterData.scheme || 'new';
    
    let province, district, ward, village;
    
    if (scheme === 'old') {
        // Lấy từ Alpine.js data hoặc hidden inputs
        province = geoFilterData.selProvOld ? 
                   document.querySelector(`option[value="${geoFilterData.selProvOld}"]`)?.textContent || '' : '';
        district = geoFilterData.selDistOld ? 
                   document.querySelector(`option[value="${geoFilterData.selDistOld}"]`)?.textContent || '' : '';
        ward = geoFilterData.selWardOld ? 
               document.querySelector(`option[value="${geoFilterData.selWardOld}"]`)?.textContent || '' : '';
        village = geoFilterData.selHamletOld ? 
                  document.querySelector(`option[value="${geoFilterData.selHamletOld}"]`)?.textContent || '' : '';
    } else {
        // Lấy từ Alpine.js data hoặc hidden inputs
        province = geoFilterData.selProvNew ? 
                   document.querySelector(`option[value="${geoFilterData.selProvNew}"]`)?.textContent || '' : '';
        district = '';
        ward = geoFilterData.selWardNew ? 
               document.querySelector(`option[value="${geoFilterData.selWardNew}"]`)?.textContent || '' : '';
        village = geoFilterData.selHamletNew ? 
                  document.querySelector(`option[value="${geoFilterData.selHamletNew}"]`)?.textContent || '' : '';
    }
    
    const geoInfo = [province, district, ward].filter(Boolean).join(' • ');
    
    document.querySelectorAll('[id$="_geo_info"]').forEach(el => {
        el.textContent = geoInfo || 'Tỉnh/Thành • Quận/Huyện • Phường/Xã';
    });
    
    // Cập nhật thông tin khu vực cho phần "Tất cả"
    document.querySelectorAll('[id$="_all_geo_info"]').forEach(el => {
        el.textContent = geoInfo || 'Tỉnh/Thành • Quận/Huyện • Phường/Xã';
    });
    
    // Load lại danh sách trường cho các cấp học tiếp theo
    loadNextLevelSchools();

    // Cập nhật trạng thái khóa khi đổi địa bàn
    refreshLockStatus();
}

// Các hàm loadSchoolsByLevel và loadSchoolsForLevel đã được xóa vì không cần chọn trường cụ thể nữa

/**
 * Lấy tên hiển thị của cấp học
 */
function getLevelName(level) {
    const levelNames = {
        'preschool': 'mầm non',
        'primary': 'tiểu học',
        'junior': 'THCS',
        'high': 'THPT'
    };
    return levelNames[level] || level;
}

// Hàm loadSchools đã được xóa vì không cần chọn trường cụ thể nữa
function loadSchools_removed(level, type) {
    // Lấy thông tin địa bàn từ scheme hiện tại
    const scheme = document.querySelector('input[name="scheme"]:checked')?.value || 'old';
    
    let provinceId, districtId, wardId;
    
    if (scheme === 'old') {
        provinceId = document.getElementById('province_id')?.value;
        districtId = document.getElementById('district_id')?.value;
        wardId = document.getElementById('ward_id')?.value;
    } else {
        provinceId = document.getElementById('province_id_new')?.value;
        districtId = '';
        wardId = document.getElementById('ward_id_new')?.value;
    }
    
    if (!provinceId) return;
    
    const params = new URLSearchParams({
        province_id: provinceId,
        district_id: districtId || '',
        ward_id: wardId || '',
        scheme: scheme
    });
    
    // Map level names to target level based on type (old/new)
    let targetLevel;
    
    if (type === 'old') {
        // Trường cũ: level hiện tại
        targetLevel = level; // Giữ nguyên level hiện tại
    } else {
        // Trường mới: level tiếp theo
        const newLevelMap = {
            'preschool': 'primary'  // Mầm non -> Tiểu học
        };
        targetLevel = newLevelMap[level];
    }
    
    fetch(`/thuc-hien-len-lop/schools/${targetLevel}?${params}`)
        .then(response => response.json())
        .then(data => {
            const selectId = type === 'old' ? `${level}_old_schools` : `${level}_new_school`;
            const select = document.getElementById(selectId);
            
            // Clear existing options except first
            select.innerHTML = type === 'old' ? '<option value="">Tất cả</option>' : '<option value="">Chọn trường</option>';
            
            data.forEach(school => {
                const option = document.createElement('option');
                option.value = school.id;
                option.textContent = `${school.code} - ${school.name}`;
                select.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error loading schools:', error);
        });
}

function loadSchoolsByScope(level, type) {
    // Kiểm tra xem có đang ở chế độ "Tất cả" không
    const checkbox = document.getElementById(level + '_specific');
    const isAllMode = !checkbox.checked;
    
    // Lấy phạm vi được chọn
    const scopeName = `${level}_${type}_scope`;
    const scopeRadios = document.querySelectorAll(`input[name="${scopeName}"]`);
    let selectedScope;
    
    if (isAllMode) {
        // Chế độ "Tất cả": lấy theo khu vực địa chính đã chọn
        selectedScope = 'geo'; // Sử dụng khu vực địa chính
    } else {
        // Chế độ "Chọn trường cụ thể": lấy theo radio button
        selectedScope = Array.from(scopeRadios).find(radio => radio.checked)?.value || 'province';
    }
    
    // Lấy thông tin địa bàn từ scheme hiện tại
    const scheme = document.querySelector('input[name="scheme"]:checked')?.value || 'old';
    
    let provinceId, districtId, wardId;
    
    if (scheme === 'old') {
        provinceId = document.getElementById('province_id')?.value;
        districtId = document.getElementById('district_id')?.value;
        wardId = document.getElementById('ward_id')?.value;
    } else {
        provinceId = document.getElementById('province_id_new')?.value;
        districtId = '';
        wardId = document.getElementById('ward_id_new')?.value;
    }
    
    if (!provinceId) return;
    
    // Xác định geo parameters dựa trên scope
    let params = new URLSearchParams({
        province_id: provinceId,
        scheme: scheme
    });
    
    // Thêm tham số scope
    if (selectedScope === 'geo') {
        // Chế độ "Tất cả": lấy theo khu vực địa chính đã chọn
        if (wardId) {
            params.set('district_id', districtId || '');
            params.set('ward_id', wardId);
            params.set('scope', 'ward');
        } else if (districtId) {
            params.set('district_id', districtId);
            params.set('scope', 'district');
        } else {
            params.set('scope', 'province');
        }
    } else {
        // Chế độ "Chọn trường cụ thể": lấy theo radio button
        params.set('scope', selectedScope);
        if (selectedScope === 'district' && districtId) {
            params.set('district_id', districtId);
        } else if (selectedScope === 'ward' && wardId) {
            params.set('district_id', districtId || '');
            params.set('ward_id', wardId);
        }
    }
    
    // Thêm parameter type vào params
    params.set('type', type);
    
    console.log(`Loading schools: ${level}, type: ${type}, params:`, params.toString());
    
    fetch(`/thuc-hien-len-lop/schools/${level}?${params}`)
        .then(response => {
            console.log(`Response status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log(`Schools data for ${level} (${type}):`, data);
            console.log(`Data length: ${data.length}`);
            if (data.length > 0) {
                console.log(`First school:`, data[0]);
            }
            const selectId = type === 'old' ? `${level}_old_schools` : `${level}_new_school`;
            const select = document.getElementById(selectId);
            console.log(`Select element:`, select);
            
            // Lưu giá trị đã chọn trước khi clear
            const selectedValue = select.value;
            
            // Clear existing options except first
            select.innerHTML = type === 'old' ? '<option value="">Tất cả</option>' : '<option value="">Chọn trường</option>';
            console.log(`Cleared select, now populating ${data.length} schools...`);
            
            data.forEach((school, index) => {
                console.log(`School ${index}:`, school);
                const option = document.createElement('option');
                option.value = school.id;
                option.textContent = `${school.code} - ${school.name}`;
                select.appendChild(option);
                console.log(`Added option: ${option.value} - ${option.textContent}`);
            });
            
            console.log(`Final select options count: ${select.options.length}`);
            
            // Khôi phục giá trị đã chọn nếu vẫn còn trong danh sách mới
            if (selectedValue && data.some(school => school.id == selectedValue)) {
                select.value = selectedValue;
            }
        })
        .catch(error => {
            console.error(`Error loading ${level} schools (${type}) by scope:`, error);
        });
}

function handleSubmit(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());
    
    // Lấy thông tin địa bàn từ geo-filter
    const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
    if (!geoFilterElement || !geoFilterElement._x_dataStack) {
        alert('Không thể lấy thông tin địa bàn');
        return;
    }
    
    const geoFilterData = geoFilterElement._x_dataStack[0];
    const scheme = geoFilterData.scheme || 'new';
    
    let provinceId, districtId, wardId, villageId;
    
    if (scheme === 'old') {
        // Lấy từ Alpine.js data hoặc hidden inputs
        provinceId = geoFilterData.selProvOld || 
                     document.querySelector('input[name="geo[province_id]"]')?.value;
        districtId = geoFilterData.selDistOld || 
                     document.querySelector('input[name="geo[district_id]"]')?.value;
        wardId = geoFilterData.selWardOld || 
                 document.querySelector('input[name="geo[ward_id]"]')?.value;
        villageId = geoFilterData.selHamletOld || 
                    document.querySelector('input[name="geo[village_id]"]')?.value;
    } else {
        // Lấy từ Alpine.js data hoặc hidden inputs
        provinceId = geoFilterData.selProvNew || 
                     document.querySelector('input[name="geo[province_id]"]')?.value;
        districtId = '';
        wardId = geoFilterData.selWardNew || 
                 document.querySelector('input[name="geo[ward_id]"]')?.value;
        villageId = geoFilterData.selHamletNew || 
                    document.querySelector('input[name="geo[village_id]"]')?.value;
    }
    
    // Debug: Log thông tin địa bàn
    console.log('Debug level transfer:', {
        scheme: scheme,
        provinceId: provinceId,
        districtId: districtId,
        wardId: wardId,
        villageId: villageId
    });
    
    // Kiểm tra đã chọn địa bàn chưa
    if (!provinceId) {
        alert('Vui lòng chọn tỉnh/thành phố');
        return;
    }
    
    if (!wardId) {
        alert('Vui lòng chọn phường/xã');
        return;
    }
    
    // Tự động tính năm học tiếp theo
    const nextAcademicYear = calculateNextAcademicYear(data.academic_year_from);
    
    // Kiểm tra đã chọn trường cho lên cấp chưa
    const nextSchoolMapping = {};
    const levels = ['preschool', 'primary', 'junior'];
    let hasSelectedSchool = false;
    
    levels.forEach(level => {
        const schoolId = data[`next_school_mapping[${level}][school_id]`];
        if (schoolId) {
            nextSchoolMapping[level] = {
                level: level,
                school_id: parseInt(schoolId)
            };
            hasSelectedSchool = true;
        }
    });
    
    // Xác nhận trước khi thực hiện
    const geoText = villageId ? `Xã: ${wardId}, Thôn: ${villageId}` : `Xã: ${wardId}`;
    const confirmMessage = `⚠️ XÁC NHẬN THỰC HIỆN LÊN LỚP VÀ LÊN CẤP ⚠️

📋 THÔNG TIN:
• Năm học từ: ${data.academic_year_from}
• Năm học đến: ${nextAcademicYear}
• Địa bàn: ${geoText}
• Giữ tên lớp: ${data.keep_class_name === 'on' ? 'Có' : 'Không'}
• Giữ đối tượng chuyển đi: ${data.keep_transfer_objects === 'on' ? 'Có' : 'Không'}

🏫 TRƯỜNG CHO LÊN CẤP:
${Object.values(nextSchoolMapping).map(mapping => 
    `• ${getLevelName(mapping.level)}: ${mapping.school_id ? 'Đã chọn' : 'Chưa chọn'}`
).join('\n')}

⚠️ LƯU Ý:
• Hệ thống sẽ lên lớp cho TẤT CẢ học sinh trong xã/phường/thôn đã chọn
• Thao tác này KHÔNG THỂ HOÀN TÁC
• Vui lòng đảm bảo dữ liệu đã chính xác

Bạn có chắc chắn muốn tiếp tục?`;

    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Lên lớp cho tất cả các cấp học trong xã/phường/thôn đã chọn
    // Không cần chọn trường cụ thể nữa
    
    const payload = {
        academic_year_from: data.academic_year_from,
        academic_year_to: nextAcademicYear,
        province_id: parseInt(provinceId),
        district_id: districtId ? parseInt(districtId) : null,
        ward_id: wardId ? parseInt(wardId) : null,
        village_id: villageId ? parseInt(villageId) : null,
        keep_class_name: data.keep_class_name === 'on',
        keep_transfer_objects: data.keep_transfer_objects === 'on',
        next_school_mapping: Object.values(nextSchoolMapping)
    };
    
    // Show loading
    document.getElementById('loadingOverlay').classList.remove('hidden');
    document.getElementById('executeBtn').disabled = true;
    
    fetch('/thuc-hien-len-lop/execute', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Thông báo thành công chi tiết
            const message = `🎉 LÊN LỚP VÀ LÊN CẤP THÀNH CÔNG! 🎉

📊 KẾT QUẢ:
• Tổng học sinh xử lý: ${data.data.total_processed}
• Tạo mới: ${data.data.total_created}
• Cập nhật: ${data.data.total_updated}
• Lỗi: ${data.data.errors ? data.data.errors.length : 0}

✅ Hệ thống đã hoàn thành việc lên lớp và lên cấp cho tất cả học sinh trong địa bàn đã chọn.`;
            
            alert(message);
            
            // Reload trang để cập nhật dữ liệu
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            alert('❌ CÓ LỖI XẢY RA!\n\n' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ CÓ LỖI XẢY RA!\n\nLỗi kết nối hoặc xử lý dữ liệu.\nVui lòng thử lại sau.');
    })
    .finally(() => {
        document.getElementById('loadingOverlay').classList.add('hidden');
        document.getElementById('executeBtn').disabled = false;
    });
}

// ========== KHÓA/MỞ KHÓA LÊN LỚP ==========
function getLockScope() {
    // Lấy thông tin địa bàn từ geo-filter
    const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
    if (!geoFilterElement || !geoFilterElement._x_dataStack) return null;
    const geoFilterData = geoFilterElement._x_dataStack[0];
    const scheme = geoFilterData.scheme || 'new';

    // Ưu tiên: school -> ward -> province
    let schoolId = document.querySelector('select[name="school_id"]')?.value || document.querySelector('input[name="school_id"]')?.value || '';
    let wardId, provinceId;
    if (scheme === 'old') {
        wardId = geoFilterData.selWardOld || document.querySelector('input[name="geo[ward_id]"]')?.value;
        provinceId = geoFilterData.selProvOld || document.querySelector('input[name="geo[province_id]"]')?.value;
    } else {
        wardId = geoFilterData.selWardNew || document.querySelector('input[name="geo[ward_id]"]')?.value;
        provinceId = geoFilterData.selProvNew || document.querySelector('input[name="geo[province_id]"]')?.value;
    }

    if (schoolId) return { scope_type: 'school', scope_id: parseInt(schoolId) };
    if (wardId) return { scope_type: 'ward', scope_id: parseInt(wardId) };
    if (provinceId) return { scope_type: 'province', scope_id: parseInt(provinceId) };
    return null;
}

function getNextYearFromSelect() {
    const from = document.getElementById('academic_year_from').value;
    return calculateNextAcademicYear(from);
}

function setLockUI(locked) {
    const status = document.getElementById('lockStatusText');
    const btn = document.getElementById('lockToggleBtn');
    const execBtn = document.getElementById('executeBtn');
    if (locked) {
        status.textContent = 'Đang KHÓA lên lớp cho phạm vi/năm này';
        status.className = 'text-sm px-2 py-1 rounded border border-red-300 text-red-700 bg-red-50';
        btn.textContent = 'Mở khóa';
        btn.disabled = false;
        execBtn.disabled = true;
        execBtn.classList.add('opacity-50','cursor-not-allowed');
    } else {
        status.textContent = 'Đang MỞ lên lớp cho phạm vi/năm này';
        status.className = 'text-sm px-2 py-1 rounded border border-green-300 text-green-700 bg-green-50';
        btn.textContent = 'Khóa';
        btn.disabled = false;
        execBtn.disabled = false;
        execBtn.classList.remove('opacity-50','cursor-not-allowed');
    }
}

function refreshLockStatus() {
    const scope = getLockScope();
    const yearTo = getNextYearFromSelect();
    const status = document.getElementById('lockStatusText');
    const btn = document.getElementById('lockToggleBtn');
    if (!scope || !yearTo) {
        status.textContent = 'Chưa đủ thông tin để kiểm tra khóa';
        btn.disabled = true;
        return;
    }
    btn.disabled = true;
    status.textContent = 'Đang kiểm tra trạng thái khóa...';
    fetch(`/thuc-hien-len-lop/lock-status?academic_year=${encodeURIComponent(yearTo)}&scope_type=${scope.scope_type}&scope_id=${scope.scope_id}&lock_type=normal`)
        .then(r => r.json())
        .then(data => {
            setLockUI(!!data.locked);
        })
        .catch(() => {
            status.textContent = 'Không kiểm tra được trạng thái khóa';
            btn.disabled = false;
        });
}

function handleLockToggle() {
    const scope = getLockScope();
    const yearTo = getNextYearFromSelect();
    if (!scope || !yearTo) {
        alert('Vui lòng chọn năm và địa bàn trước khi khóa/mở khóa.');
        return;
    }
    const statusText = document.getElementById('lockStatusText').textContent || '';
    const isLocked = statusText.includes('KHÓA');
    const url = isLocked ? '/thuc-hien-len-lop/unlock' : '/thuc-hien-len-lop/lock';
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            academic_year: yearTo,
            scope_type: scope.scope_type,
            scope_id: scope.scope_id,
            lock_type: 'normal'
        })
    }).then(async r => {
        if (!r.ok) {
            const d = await r.json().catch(() => ({}));
            throw new Error(d.message || 'Thao tác thất bại');
        }
        return r.json();
    })
    .then(() => {
        refreshLockStatus();
    })
    .catch(err => {
        alert('Không thể thực hiện thao tác: ' + err.message);
    });
}


</script>


@push('scripts')
<script>
// Additional scripts if needed
</script>
@endpush
@endsection
