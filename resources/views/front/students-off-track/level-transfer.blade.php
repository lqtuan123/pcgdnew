@extends('layouts.front')

@section('title', 'Lên lớp học sinh trái tuyến')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Lên lớp học sinh trái tuyến</h1>
        <p class="text-gray-600">Thực hiện lên lớp cho học sinh trái tuyến từ năm học này sang năm học tiếp theo</p>
        <div class="mt-3 flex items-center gap-3">
            <span id="lockStatusText" class="text-sm px-2 py-1 rounded border border-gray-300 text-gray-700">Đang kiểm tra trạng thái khóa...</span>
            <span id="lockUserInfo" class="text-xs text-gray-500 hidden"></span>
            <button type="button" id="lockToggleBtn" class="text-sm bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded border border-gray-300" disabled>Bấm khóa</button>
        </div>
    </div>

    <!-- Form -->
    <form id="levelTransferForm" class="bg-white rounded-lg shadow-md p-6">
        @csrf
        
        <!-- Năm học -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Chọn năm học</label>
                <select name="academic_year_from" id="academic_year_from" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    @foreach($academicYears as $academicYear)
                        <option value="{{ $academicYear }}" {{ $academicYear == $currentAcademicYear ? 'selected' : '' }}>
                            {{ $academicYear }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Năm học tiếp theo</label>
                <input type="text" name="academic_year_to" id="academic_year_to" 
                       value="{{ $nextAcademicYear }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50" 
                       readonly>
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

        <!-- Chọn trường cho từng cấp học -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Chọn trường cho từng cấp học</h3>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <h4 class="font-medium text-blue-900 mb-2">Lưu ý:</h4>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li>• Học sinh không chuyển cấp sẽ giữ nguyên mã trường</li>
                    <li>• Học sinh chuyển cấp sẽ được chuyển đến trường đã chọn</li>
                    <li>• Bậc tốt nghiệp sẽ được cập nhật tự động</li>
                </ul>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Mầm non -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-3">Mầm non</h4>
                    <p class="text-sm text-gray-600 mb-3">Chọn trường TH cho học sinh 5-6 tuổi</p>
                    <select name="school_mappings[0][level]" class="hidden" value="kindergarten">
                        <option value="kindergarten">Mầm non</option>
                    </select>
                    <select name="school_mappings[0][school_id]" id="kindergarten_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Chọn trường TH...</option>
                    </select>
                    <div class="mt-2 text-xs text-gray-500">
                        <span id="kindergarten_count">0</span> học sinh sẽ chuyển cấp
                    </div>
                </div>

                <!-- Tiểu học -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-3">Tiểu học</h4>
                    <p class="text-sm text-gray-600 mb-3">Chọn trường THCS cho học sinh lớp 5</p>
                    <select name="school_mappings[1][level]" class="hidden" value="primary">
                        <option value="primary">Tiểu học</option>
                    </select>
                    <select name="school_mappings[1][school_id]" id="primary_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Chọn trường THCS...</option>
                    </select>
                    <div class="mt-2 text-xs text-gray-500">
                        <span id="primary_count">0</span> học sinh sẽ chuyển cấp
                    </div>
                </div>

                <!-- THCS -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-3">THCS</h4>
                    <p class="text-sm text-gray-600 mb-3">Chọn trường THPT cho học sinh lớp 9</p>
                    <select name="school_mappings[2][level]" class="hidden" value="junior">
                        <option value="junior">THCS</option>
                    </select>
                    <select name="school_mappings[2][school_id]" id="junior_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <option value="">Chọn trường THPT...</option>
                    </select>
                    <div class="mt-2 text-xs text-gray-500">
                        <span id="junior_count">0</span> học sinh sẽ chuyển cấp
                    </div>
                </div>

                <!-- THPT -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-800 mb-3">THPT</h4>
                    <p class="text-sm text-gray-600 mb-3">Học sinh lớp 12 sẽ tốt nghiệp</p>
                    <select name="school_mappings[3][level]" class="hidden" value="high">
                        <option value="high">THPT</option>
                    </select>
                    <select name="school_mappings[3][school_id]" id="high_school" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-100" disabled>
                        <option value="0">Tốt nghiệp THPT</option>
                    </select>
                    <div class="mt-2 text-xs text-gray-500">
                        <span id="high_count">0</span> học sinh sẽ tốt nghiệp
                    </div>
                </div>
            </div>
        </div>

        <!-- Nút thực hiện -->
        <div class="flex justify-end">
            <button type="submit" id="executeBtn" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                Thực hiện lên lớp
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
                    📚 Hệ thống đang xử lý học sinh trái tuyến<br>
                    ⏱️ Quá trình này có thể mất vài phút tùy thuộc vào số lượng học sinh
                </p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup event listeners
    setupGeoListeners();
    setupSchoolListeners();
    setupAcademicYearListener();
    
    // Xử lý submit form
    document.getElementById('levelTransferForm').addEventListener('submit', handleSubmit);
    
    // Thử load trường ngay sau khi DOM ready
    setTimeout(() => {
        if (!checkAndLoadSchools()) {
            // Nếu chưa load được, thử lại sau
            setTimeout(() => {
                checkAndLoadSchools();
            }, 2000);
        }
    }, 1000);
    
    // Sự kiện khóa/mở khóa
    document.getElementById('lockToggleBtn').addEventListener('click', handleLockToggle);

    // Kiểm tra trạng thái khóa ban đầu
    refreshLockStatus();
    
    // Cập nhật năm học tiếp theo khi trang load lần đầu
    const initialYear = document.getElementById('academic_year_from').value;
    const nextYear = calculateNextAcademicYear(initialYear);
    document.getElementById('academic_year_to').value = nextYear;
});

function setupGeoListeners() {
    // Lắng nghe thay đổi geo-filter
    const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
    if (geoFilterElement && geoFilterElement._x_dataStack) {
        const geoFilterData = geoFilterElement._x_dataStack[0];
        
        // Lắng nghe thay đổi scheme
        geoFilterData.$watch('scheme', (newScheme) => {
            loadSchoolsForAllLevels();
        });
        
        // Lắng nghe thay đổi tỉnh/xã
        geoFilterData.$watch('selProvNew', () => {
            loadSchoolsForAllLevels();
            refreshLockStatus();
        });
        geoFilterData.$watch('selWardNew', () => {
            loadSchoolsForAllLevels();
            refreshLockStatus();
        });
        geoFilterData.$watch('selHamletNew', () => {
            loadSchoolsForAllLevels();
        });
        
        // Load trường ngay khi geo-filter đã được khởi tạo
        setTimeout(() => {
            checkAndLoadSchools();
        }, 500); // Đợi 500ms để geo-filter khởi tạo xong
    } else {
        // Nếu không tìm thấy geo-filter ngay, thử lại sau
        setTimeout(() => {
            setupGeoListeners();
        }, 1000);
    }
}

function setupSchoolListeners() {
    // Lắng nghe thay đổi trường để cập nhật số lượng học sinh
    document.getElementById('kindergarten_school').addEventListener('change', updateStudentCounts);
    document.getElementById('primary_school').addEventListener('change', updateStudentCounts);
    document.getElementById('junior_school').addEventListener('change', updateStudentCounts);
    document.getElementById('high_school').addEventListener('change', updateStudentCounts);
}

function setupAcademicYearListener() {
    // Lắng nghe thay đổi năm học để cập nhật năm học tiếp theo
    document.getElementById('academic_year_from').addEventListener('change', function() {
        const selectedYear = this.value;
        const nextYear = calculateNextAcademicYear(selectedYear);
        document.getElementById('academic_year_to').value = nextYear;
        
        // Cập nhật trạng thái khóa khi thay đổi năm học
        refreshLockStatus();
    });
}

function toggleScheme(scheme) {
    // Geo-filter sẽ tự động xử lý scheme switching
    loadSchoolsForAllLevels();
}

// Các hàm load geo units đã được thay thế bởi geo-filter component

function loadSchoolsForAllLevels() {
    loadSchoolsByLevel('kindergarten');
    loadSchoolsByLevel('primary');
    loadSchoolsByLevel('junior');
    loadSchoolsByLevel('high');
}

// Hàm kiểm tra và load trường khi geo-filter sẵn sàng
function checkAndLoadSchools() {
    const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
    if (geoFilterElement && geoFilterElement._x_dataStack) {
        const geoFilterData = geoFilterElement._x_dataStack[0];
        const scheme = geoFilterData.scheme || 'new';
        
        let provinceId, wardId;
        if (scheme === 'old') {
            provinceId = geoFilterData.selProvOld;
            wardId = geoFilterData.selWardOld;
        } else {
            provinceId = geoFilterData.selProvNew;
            wardId = geoFilterData.selWardNew;
        }
        
        if (provinceId) {
            loadSchoolsForAllLevels();
            return true;
        }
    }
    return false;
}

function loadSchoolsByLevel(level) {
    // Lấy geo data từ geo-filter
    const geoFilterElement = document.querySelector('[x-data*="geoPicker"]');
    if (!geoFilterElement || !geoFilterElement._x_dataStack) {
        return;
    }
    
    const geoFilterData = geoFilterElement._x_dataStack[0];
    const scheme = geoFilterData.scheme || 'new';
    
    let provinceId, districtId, wardId;
    
    if (scheme === 'old') {
        provinceId = geoFilterData.selProvOld;
        districtId = geoFilterData.selDistOld;
        wardId = geoFilterData.selWardOld;
    } else {
        provinceId = geoFilterData.selProvNew;
        districtId = '';
        wardId = geoFilterData.selWardNew;
    }
    
    if (!provinceId) {
        return;
    }
    
    const params = new URLSearchParams({
        province_id: provinceId,
        district_id: districtId || '',
        ward_id: wardId || '',
        scheme: scheme,
        level: level
    });
    
    fetch(`/hoc-sinh-trai-tuyen/level-transfer/schools-by-level?${params}`)
        .then(response => response.json())
        .then(data => {
            const selectId = level === 'kindergarten' ? 'kindergarten_school' :
                           level === 'primary' ? 'primary_school' : 
                           level === 'junior' ? 'junior_school' : 'high_school';
            const select = document.getElementById(selectId);
            
            // Lưu giá trị đã chọn trước khi clear
            const selectedValue = select.value;
            
            // Clear existing options except first
            if (level === 'high') {
                select.innerHTML = '<option value="0">Tốt nghiệp THPT</option>';
            } else {
                const levelText = level === 'kindergarten' ? 'TH' : 
                                level === 'primary' ? 'THCS' : 'THPT';
                select.innerHTML = `<option value="">Chọn trường ${levelText}...</option>`;
            }
            
            if (level !== 'high') {
                data.forEach(school => {
                    const option = document.createElement('option');
                    option.value = school.id;
                    option.textContent = `${school.code} - ${school.name}`;
                    select.appendChild(option);
                });
            }
            
            // Khôi phục giá trị đã chọn nếu vẫn còn trong danh sách mới
            if (selectedValue && data.some(school => school.id == selectedValue)) {
                select.value = selectedValue;
            }
            
            // Cập nhật số lượng học sinh
            updateStudentCounts();
        })
        .catch(error => {
            // Error loading schools
        });
}

function updateStudentCounts() {
    // TODO: Implement API call to get student counts for each level
    // For now, just show placeholder
    document.getElementById('kindergarten_count').textContent = '0';
    document.getElementById('primary_count').textContent = '0';
    document.getElementById('junior_count').textContent = '0';
    document.getElementById('high_count').textContent = '0';
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

function setLockUI(locked, userInfo = null) {
    const status = document.getElementById('lockStatusText');
    const userInfoSpan = document.getElementById('lockUserInfo');
    const btn = document.getElementById('lockToggleBtn');
    const execBtn = document.getElementById('executeBtn');
    
    if (locked) {
        status.textContent = 'Đang KHÓA lên lớp cho phạm vi/năm này';
        status.className = 'text-sm px-2 py-1 rounded border border-red-300 text-red-700 bg-red-50';
        
        // Hiển thị thông tin người khóa
        if (userInfo && userInfo.locked_by_user) {
            const userName = userInfo.locked_by_user.name || 'N/A';
            const userEmail = userInfo.locked_by_user.email || 'N/A';
            userInfoSpan.textContent = `🔒 Khóa bởi: ${userName} (${userEmail})`;
            userInfoSpan.classList.remove('hidden');
        } else {
            userInfoSpan.classList.add('hidden');
        }
        
        btn.textContent = 'Mở khóa';
        btn.disabled = false;
        execBtn.disabled = true;
        execBtn.classList.add('opacity-50','cursor-not-allowed');
    } else {
        status.textContent = 'Đang MỞ lên lớp cho phạm vi/năm này';
        status.className = 'text-sm px-2 py-1 rounded border border-green-300 text-green-700 bg-green-50';
        
        // Ẩn thông tin người khóa
        userInfoSpan.classList.add('hidden');
        
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
    fetch(`/hoc-sinh-trai-tuyen/level-transfer/lock-status?academic_year=${encodeURIComponent(yearTo)}&scope_type=${scope.scope_type}&scope_id=${scope.scope_id}&lock_type=trai_tuyen`)
        .then(r => r.json())
        .then(data => {
            setLockUI(!!data.locked, data);
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
    const url = isLocked ? '/hoc-sinh-trai-tuyen/level-transfer/unlock' : '/hoc-sinh-trai-tuyen/level-transfer/lock';
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
            lock_type: 'trai_tuyen'
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
    
    let provinceId, districtId, wardId;
    
    if (scheme === 'old') {
        provinceId = geoFilterData.selProvOld;
        districtId = geoFilterData.selDistOld;
        wardId = geoFilterData.selWardOld;
    } else {
        provinceId = geoFilterData.selProvNew;
        districtId = '';
        wardId = geoFilterData.selWardNew;
    }
    
    // Kiểm tra đã chọn địa bàn chưa
    if (!provinceId) {
        alert('Vui lòng chọn tỉnh/thành phố');
        return;
    }
    
    if (!wardId) {
        alert('Vui lòng chọn phường/xã');
        return;
    }
    
    // Kiểm tra đã chọn trường chưa
    const kindergartenSchool = document.getElementById('kindergarten_school').value;
    const primarySchool = document.getElementById('primary_school').value;
    const juniorSchool = document.getElementById('junior_school').value;
    
    if (!kindergartenSchool || !primarySchool || !juniorSchool) {
        alert('Vui lòng chọn đầy đủ trường cho các cấp học');
        return;
    }
    
    // Xác nhận trước khi thực hiện
    const confirmMessage = `⚠️ XÁC NHẬN THỰC HIỆN LÊN LỚP ⚠️

📋 THÔNG TIN:
• Năm học từ: ${data.academic_year_from}
• Năm học đến: ${data.academic_year_to}
• Địa bàn: ${wardId}
• Trường TH: ${document.getElementById('kindergarten_school').selectedOptions[0]?.textContent || 'Chưa chọn'}
• Trường THCS: ${document.getElementById('primary_school').selectedOptions[0]?.textContent || 'Chưa chọn'}
• Trường THPT: ${document.getElementById('junior_school').selectedOptions[0]?.textContent || 'Chưa chọn'}

⚠️ LƯU Ý:
• Hệ thống sẽ lên lớp cho TẤT CẢ học sinh trái tuyến trong địa bàn đã chọn
• Học sinh không chuyển cấp sẽ giữ nguyên trường
• Học sinh chuyển cấp sẽ chuyển đến trường đã chọn
• Thao tác này KHÔNG THỂ HOÀN TÁC

Bạn có chắc chắn muốn tiếp tục?`;

    if (!confirm(confirmMessage)) {
        return;
    }
    
    const payload = {
        academic_year_from: data.academic_year_from,
        academic_year_to: data.academic_year_to,
        province_id: parseInt(provinceId),
        district_id: districtId ? parseInt(districtId) : null,
        ward_id: wardId ? parseInt(wardId) : null,
        scheme: scheme,
        school_mappings: [
            {
                level: 'kindergarten',
                school_id: parseInt(kindergartenSchool)
            },
            {
                level: 'primary',
                school_id: parseInt(primarySchool)
            },
            {
                level: 'junior',
                school_id: parseInt(juniorSchool)
            },
            {
                level: 'high',
                school_id: 0 // Tốt nghiệp
            }
        ]
    };
    
    // Show loading
    document.getElementById('loadingOverlay').classList.remove('hidden');
    document.getElementById('executeBtn').disabled = true;
    
    fetch('/hoc-sinh-trai-tuyen/level-transfer/execute', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify(payload)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Server returned invalid JSON');
            }
        });
    })
    .then(data => {
        if (data.success) {
            // Thông báo thành công chi tiết
            const message = `🎉 LÊN LỚP THÀNH CÔNG! 🎉

📊 KẾT QUẢ:
• Tổng học sinh xử lý: ${data.data.total_processed}
• Tạo mới: ${data.data.total_created}
• Cập nhật: ${data.data.total_updated}
• Lỗi: ${data.data.errors ? data.data.errors.length : 0}

✅ Hệ thống đã hoàn thành việc lên lớp cho tất cả học sinh trái tuyến trong địa bàn đã chọn.`;
            
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
        alert('❌ CÓ LỖI XẢY RA!\n\nLỗi kết nối hoặc xử lý dữ liệu.\nVui lòng thử lại sau.');
    })
    .finally(() => {
        document.getElementById('loadingOverlay').classList.add('hidden');
        document.getElementById('executeBtn').disabled = false;
    });
}
</script>

@push('scripts')
<script>
// Additional scripts if needed
</script>
@endpush
@endsection
