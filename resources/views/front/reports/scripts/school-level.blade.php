<script>
// Function để mở modal (được gọi từ onclick)
function openSchoolDataModal(schoolId = 0, wardId = 0) {
    const modal = document.getElementById('schoolDataModal');
    if (modal) {
        // Điền school_id và ward_id
        const schoolIdInput = document.querySelector('input[name="school_id"]');
        const wardIdInput = document.querySelector('input[name="ward_id"]');
        const academicYearInput = document.querySelector('input[name="academic_year"]');
        
        if (schoolIdInput) schoolIdInput.value = schoolId;
        if (wardIdInput) wardIdInput.value = wardId;
        
        // Điền năm học hiện tại
        if (academicYearInput) {
            const currentYear = new Date().getFullYear();
            academicYearInput.value = currentYear;
        }
        
        // Load dữ liệu từ bảng (nếu có)
        loadDataFromTable();
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Ngăn scroll
    }
}

// Function để load dữ liệu từ bảng
function loadDataFromTable() {
    // Tìm dòng dữ liệu trong bảng (nếu có)
    const tableRows = document.querySelectorAll('tbody tr');
    if (tableRows.length > 0) {
        // Lấy dữ liệu từ dòng đầu tiên (cấp trường chỉ có 1 dòng)
        const firstRow = tableRows[0];
        const cells = firstRow.querySelectorAll('td');
        
        if (cells.length > 0) {
            // Tạo object dữ liệu từ bảng theo cấu trúc Kindergarten Report
            const data = {
                // CBQL, giáo viên, nhân viên
                total_all: extractNumberFromCell(cells[1]), // Tổng số CBQL, GV, NV
                contract_work_all: extractNumberFromCell(cells[2]), // Hợp đồng làm việc
                contract_labor_all: extractNumberFromCell(cells[3]), // Hợp đồng lao động - Tổng số
                cs_policy: extractNumberFromCell(cells[4]), // Được hưởng CĐ, CS theo quy định
                total_cbql: extractNumberFromCell(cells[5]), // CBQL - Tổng số
                vice_principal_cbql: extractNumberFromCell(cells[6]), // Phó Hiệu trưởng
                total_teachers: extractNumberFromCell(cells[7]), // Giáo viên - Tổng số
                ethnic_minority: extractNumberFromCell(cells[8]), // Dân tộc thiểu số
                teacher_ratio: extractNumberFromCell(cells[9]), // Tỷ lệ GV/Lớp
                total_staff: extractNumberFromCell(cells[10]), // Nhân viên - Tổng số
                
                // Giáo viên dạy MG 5 tuổi
                total_k5: extractNumberFromCell(cells[11]), // GV dạy MG 5 tuổi - Tổng số
                contract_work_k5: extractNumberFromCell(cells[12]), // Hợp đồng làm việc K5
                contract_labor_k5: extractNumberFromCell(cells[13]), // Hợp đồng lao động K5 - Tổng số
                cs_policy_k5: extractNumberFromCell(cells[14]), // Hợp đồng lao động K5 - Được hưởng CĐ, CS
                teacher_ratio_k5: extractNumberFromCell(cells[15]), // Tỷ lệ GV/Lớp K5
                training_standard: extractNumberFromCell(cells[16]), // Đạt chuẩn
                training_above_standard: extractNumberFromCell(cells[17]), // Trên chuẩn
                standard_profession: extractNumberFromCell(cells[18]) // Đạt chuẩn nghề nghiệp
            };
            
            loadDataToModal(data);
        }
    }
}

// Function để extract số từ cell
function extractNumberFromCell(cell) {
    if (!cell) return 0;
    const span = cell.querySelector('span');
    const text = span ? span.textContent : cell.textContent;
    const number = parseFloat(text.replace(/[^\d.-]/g, ''));
    return isNaN(number) ? 0 : number;
}

// Function để lấy tên trường từ bảng
function getSchoolNameFromTable() {
    const tableRows = document.querySelectorAll('tbody tr');
    if (tableRows.length > 0) {
        const firstRow = tableRows[0];
        const cells = firstRow.querySelectorAll('td');
        // Tên trường ở cột thứ 2 (index 1) - sau cột TT
        if (cells.length > 1) {
            return cells[1].textContent.trim();
        }
    }
    return null;
}

// Function để lấy tên trường từ select
function getSchoolNameFromSelect() {
    const schoolSelect = document.querySelector('select[name="geo[school_id]"]');
    if (schoolSelect && schoolSelect.selectedIndex > 0) {
        return schoolSelect.options[schoolSelect.selectedIndex].text;
    }
    return null;
}

// Function để lấy hạng trường từ bảng
function getSchoolRankFromTable() {
    const tableRows = document.querySelectorAll('tbody tr');
    if (tableRows.length > 0) {
        const firstRow = tableRows[0];
        const cells = firstRow.querySelectorAll('td');
        // Hạng trường ở cột thứ 3 (index 2) - sau cột TT và tên trường
        if (cells.length > 2) {
            const rankText = cells[2].textContent.trim();
            const rank = parseInt(rankText);
            return isNaN(rank) ? 0 : rank;
        }
    }
    return 0;
}

// Function để đóng modal (được gọi từ onclick)
function closeSchoolDataModal() {
    const modal = document.getElementById('schoolDataModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto'; // Khôi phục scroll
    }
}

// Function để load dữ liệu vào modal (nếu có dữ liệu từ bảng)
function loadDataToModal(data) {
    if (!data) return;
    
    // Load dữ liệu vào các input theo cấu trúc Kindergarten Report
    const fields = [
        // CBQL, giáo viên, nhân viên
        'total_all', 'contract_work_all', 'contract_labor_all', 'cs_policy',
        'total_cbql', 'vice_principal_cbql', 'total_teachers', 'ethnic_minority',
        'teacher_ratio', 'total_staff',
        
        // Giáo viên dạy MG 5 tuổi
        'total_k5', 'contract_work_k5', 'contract_labor_k5', 'cs_policy_k5',
        'teacher_ratio_k5', 'training_standard', 'training_above_standard', 'standard_profession'
    ];
    
    fields.forEach(field => {
        const input = document.querySelector(`input[name="${field}"]`);
        if (input && data[field] !== undefined) {
            input.value = data[field];
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing modal...');
    
    const modal = document.getElementById('schoolDataModal');
    const form = document.getElementById('schoolDataForm');
    const saveButton = document.getElementById('saveData');
    const resetButton = document.getElementById('resetForm');
    
    console.log('Modal:', modal);
    console.log('Form:', form);
    console.log('Save Button:', saveButton);
    console.log('Reset Button:', resetButton);
    
    // Mở modal (backup method)
    const editBtn = document.getElementById('editDataBtn');
    console.log('Edit Button:', editBtn);
    
    if (editBtn && modal) {
        editBtn.addEventListener('click', function() {
            console.log('Edit button clicked');
            openSchoolDataModal();
        });
    } else {
        console.log('Edit button or modal not found');
    }
    
    // Đóng modal
    function closeModalFunc() {
        closeSchoolDataModal();
    }
    
    const closeModal = document.getElementById('closeModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    
    if (closeModal) {
        closeModal.addEventListener('click', closeModalFunc);
    }
    
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModalFunc);
    }
    
    // Đóng modal khi click outside
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeSchoolDataModal();
            }
        });
    }
    
    // Đóng modal khi nhấn Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            closeSchoolDataModal();
        }
    });
    
    // Xử lý submit form
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Hiển thị loading
            const originalText = saveButton.textContent;
            saveButton.textContent = 'Đang lưu...';
            saveButton.disabled = true;
            
            // Lấy dữ liệu form
            const formData = new FormData(form);
            
            // Gửi AJAX request
            fetch('{{ route("front.kindergarten-reports.save-school-data") }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hiển thị thông báo thành công
                    showNotification('Lưu dữ liệu thành công!', 'success');
                    
                    // Đóng modal
                    closeModalFunc();
                    
                    // Reload trang để cập nhật dữ liệu
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    // Hiển thị thông báo lỗi
                    showNotification(data.message || 'Có lỗi xảy ra khi lưu dữ liệu!', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Có lỗi xảy ra khi lưu dữ liệu!', 'error');
            })
            .finally(() => {
                // Khôi phục button
                saveButton.textContent = originalText;
                saveButton.disabled = false;
            });
        });
    }
    
    // Xử lý nút làm mới
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            if (confirm('Bạn có chắc chắn muốn làm mới form? Tất cả dữ liệu đã nhập sẽ bị mất.')) {
                if (form) {
                    form.reset();
                }
            }
        });
    }
    
    // Hàm hiển thị thông báo
    function showNotification(message, type = 'info') {
        // Tạo element thông báo
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg text-white ${
            type === 'success' ? 'bg-green-500' : 
            type === 'error' ? 'bg-red-500' : 
            'bg-blue-500'
        }`;
        notification.textContent = message;
        
        // Thêm vào DOM
        document.body.appendChild(notification);
        
        // Tự động ẩn sau 3 giây
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }
});
</script>
