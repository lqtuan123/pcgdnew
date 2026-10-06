@extends('layouts.front')
@section('title','Danh sách học sinh mầm non')

@section('content')
<style>
.th{padding:.5rem .75rem;font-weight:600;border-bottom:1px solid #e5e7eb}
.td{padding:.5rem .75rem;border-bottom:1px solid #f1f5f9;white-space:nowrap}

/* Cải thiện thanh cuộn */
.table-container {
  max-height: 70vh;
  overflow-y: auto;
  overflow-x: auto;
}

/* Responsive cho mobile */
@media (max-width: 768px) {
  .table-container {
    max-height: 60vh;
  }
  
  .th, .td {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
  }
}

.table-container::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

.table-container::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

/* Sticky columns - Tính toán chính xác theo chiều rộng cột */
.sticky-left-0 { left: 0; }        /* Checkbox: 0rem */
.sticky-left-12 { left: 3rem; }    /* TT: 0 + 3rem = 3rem */
.sticky-left-24 { left: 6rem; }    /* Họ đệm: 3 + 3rem = 6rem */
.sticky-left-44 { left: 14rem; }   /* Tên: 6 + 8rem = 14rem */
.sticky-left-56 { left: 20rem; }   /* Ngày: 14 + 6rem = 20rem */

/* Custom select styling */
.custom-select {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.5rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
}
</style>

<div class="mx-auto max-w-7xl px-4 py-6">
  <div class="mb-4 flex items-center justify-between py-3">
    <h1 class="text-xl font-semibold">Danh sách học sinh mầm non</h1>
                <div class="flex gap-2">
                  @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_mam_non', 'delete'))
                    <button type="button" id="bulk-delete-btn"
                            class="rounded-lg bg-red-600 px-3 py-2 text-white hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                            disabled>
                      Xóa đã chọn
                    </button>
                  @endif
                  @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_mam_non', 'create'))
                    <a href="{{ route('front.preschool-students.import') }}"
                      class="rounded-lg bg-slate-600 px-3 py-2 text-white hover:bg-blue-700">
                      Nhập Excel
                    </a>
                  @endif
                  @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_mam_non', 'view'))
                    <a href="{{ route('front.preschool-students.export', request()->query()) }}"
                       class="rounded-lg bg-emerald-600 px-3 py-2 text-white hover:bg-emerald-700">
                      Xuất Excel
                    </a>
                  @endif
                </div>
  </div>

  <!-- Thông báo hướng dẫn -->
  <div class="mb-4 rounded-lg bg-blue-50 p-3 text-blue-800">
    <div class="flex items-center cursor-pointer" onclick="toggleGuide()">
      <svg class="mr-2 h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
      </svg>
      <h3 class="font-medium">Hướng dẫn sử dụng</h3>
      <svg id="guide-arrow" class="ml-auto h-4 w-4 text-blue-600 transform transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
      </svg>
    </div>
    <div id="guide-content" class="mt-3 hidden">
      <p class="text-sm">
        Học sinh mầm non không được tạo trực tiếp. Để thêm học sinh mầm non, vui lòng:
      </p>
      <ol class="mt-2 ml-4 list-decimal text-sm">
        <li>Tạo phiếu điều tra mới hoặc sử dụng phiếu có sẵn</li>
        <li>Thêm thành viên hộ gia đình với khối học mầm non (dưới 5 tuổi)</li>
        <li>Hệ thống sẽ tự động thêm dữ liệu mầm non cho trẻ</li>
      </ol>
    </div>
  </div>

  <!-- Bộ lọc -->
  <form method="get" class="mb-3 grid gap-3 md:grid-cols-12">
    @php
      $base = $defaultYear ?? \App\Models\SurveyForm::currentAcademicYear();
      [$y1,$y2] = explode('-', $base);
      
      // Tạo danh sách năm học từ 2000-2001 đến hơn 1 năm so với năm hiện tại
      $years = [];
      $startYear = 2000;
      $endYear = $y2 + 1; // Hơn 1 năm so với năm hiện tại
      
      for ($year = $startYear; $year <= $endYear; $year++) {
        $years[] = $year . '-' . ($year + 1);
      }
      
      // Sắp xếp từ mới nhất đến cũ nhất
      $years = array_reverse($years);
    @endphp
    
    <div class="md:col-span-4">
      <label class="block text-xs text-slate-500 mb-1">Năm học</label>
      <select name="academic_year" class="w-full rounded-lg border px-3 py-2">
        <option value="all" @selected($academicYear==='all')>— Tất cả —</option>
        @foreach($years as $yy)
          <option value="{{ $yy }}" @selected($yy==$academicYear)>{{ $yy }}</option>
        @endforeach
      </select>
      
      <div class="mt-2">
        <label class="block text-xs text-slate-500 mb-1">Tìm kiếm</label>
        <input name="search" value="{{ $search }}" class="w-full rounded-lg border px-3 py-2" placeholder="Tên, CMND, điện thoại">
      </div>
      
      <div class="mt-2">
        <label class="block text-xs text-slate-500 mb-1">Độ tuổi</label>
        <select name="age_filter" class="w-full rounded-lg border px-3 py-2">
          <option value="all" @selected($ageFilter==='all')>— Tất cả —</option>
          <option value="1" @selected($ageFilter==='1')>1 tuổi</option>
          <option value="2" @selected($ageFilter==='2')>2 tuổi</option>
          <option value="3" @selected($ageFilter==='3')>3 tuổi</option>
          <option value="4" @selected($ageFilter==='4')>4 tuổi</option>
          <option value="5" @selected($ageFilter==='5')>5 tuổi</option>
        </select>
      </div>
    </div>

    <div class="md:col-span-8">
      <label class="block text-xs text-slate-500 mb-1">Khu vực</label>
      @include('partials.front.geo-filter', [
        'scheme' => $scheme ?? 'old',
        'geo' => $geoData ?? [],
        'showVillage' => true
      ])
    </div>

    <div class="md:col-span-12">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <label class="text-sm text-gray-600">Hiển thị:</label>
          <select name="per_page" class="custom-select rounded border px-2 py-1 text-sm">
            <option value="10" {{ request('per_page') == '10' ? 'selected' : '' }}>10</option>
            <option value="20" {{ request('per_page') == '20' || !request('per_page') ? 'selected' : '' }}>20</option>
            <option value="50" {{ request('per_page') == '50' ? 'selected' : '' }}>50</option>
            <option value="100" {{ request('per_page') == '100' ? 'selected' : '' }}>100</option>
          </select>
          <span class="text-sm text-gray-600">dòng/trang</span>
          <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
            Lọc dữ liệu
          </button>
        </div>
        <div class="flex items-center gap-2">
          @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_mam_non', 'edit'))
            <button type="button" id="bulk-update-study-btn"
                    class="rounded-lg bg-orange-600 px-4 py-2 text-white hover:bg-orange-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                    disabled>
              Bổ sung thông tin học tập
            </button>
            <button type="button" id="bulk-update-gdmn-btn"
                    class="rounded-lg bg-purple-600 px-4 py-2 text-white hover:bg-purple-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                    disabled>
              Bổ sung hoàn thành GDMN
            </button>
          @endif
        </div>
      </div>
    </div>
  </form>

  <!-- Bảng danh sách -->
  <div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="table-container">
      <table class="min-w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="th sticky left-0 bg-gray-50 z-10 w-12">
              <input type="checkbox" id="select-all" class="rounded border-gray-300">
            </th>
            <th class="th sticky left-12 bg-gray-50 z-10 w-12">TT</th>
            <th class="th sticky left-24 bg-gray-50 z-10 w-32">Họ đệm</th>
            <th class="th sticky left-44 bg-gray-50 z-10 w-24">Tên</th>
            <th class="th w-16">Ngày</th>
            <th class="th w-16">Tháng</th>
            <th class="th w-20">Năm sinh</th>
            <th class="th w-16">Nữ</th>
            <th class="th w-24">Dân tộc</th>
            <th class="th w-24">Khối học</th>
            <th class="th w-24">Lớp học</th>
            <th class="th w-32">Mã trường</th>
            <th class="th w-20">Học 2 buổi</th>
            <th class="th w-20">Học bán trú</th>
            <th class="th w-24">Học đủ ngày</th>
            <th class="th w-20">Chuyên cần</th>
            <th class="th w-24">Theo dõi cân nặng</th>
            <th class="th w-20">SDD nhẹ cân</th>
            <th class="th w-24">Theo dõi chiều cao</th>
            <th class="th w-20">SDD thấp còi</th>
            <th class="th w-24">Dân tộc chuẩn bị TV</th>
            <th class="th w-24">Hoàn thành GDMN</th>
            <th class="th w-32">Họ tên chủ hộ</th>
            <th class="th w-48">Địa chỉ</th>
            <th class="th w-32">Tổ/Thôn/Ấp</th>
            <th class="th w-24">Số phiếu</th>
            <th class="th w-32">Họ tên Cha/Mẹ</th>
            <th class="th w-24">Điện thoại</th>
            <th class="th w-20">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          @forelse($students as $index => $student)
            <tr class="hover:bg-gray-50">
              <td class="td sticky left-0 bg-white z-10 border-r border-gray-200">
                <input type="checkbox" name="selected_ids[]" value="{{ $student->id }}" class="student-checkbox rounded border-gray-300">
              </td>
              <td class="td sticky left-12 bg-white z-10 border-r border-gray-200">
                {{ $students->firstItem() + $index }}
              </td>
              <td class="td sticky left-24 bg-white z-10 border-r border-gray-200">
                {{ $student->person->ho_dem ?? '—' }}
              </td>
              <td class="td sticky left-44 bg-white z-10 border-r border-gray-200">
                {{ $student->person->ten ?? '—' }}
              </td>
              <td class="td">{{ $student->person->dob_day ? sprintf('%02d', $student->person->dob_day) : '—' }}</td>
              <td class="td">{{ $student->person->dob_month ? sprintf('%02d', $student->person->dob_month) : '—' }}</td>
              <td class="td">{{ $student->person->dob_year ?? '—' }}</td>
              <td class="td text-center">
                @if($student->person->is_female)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td">{{ $student->person->ethnicity->name ?? '—' }}</td>
              <td class="td">{{ $student->education->block->name ?? '—' }}</td>
              <td class="td">{{ $student->education->class_label ?? '—' }}</td>
              <td class="td">{{ $student->education->school->code ?? '—' }}</td>
              <td class="td text-center">
                @if($student->personMamnon->hoc_2_buoi)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->hoc_ban_tru)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->hoc_du_ngay_theo_quy_dinh)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->chuyen_can)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->theo_doi_bd_can_nang)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->sdd_nhe_can)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->theo_doi_bd_chieu_cao)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->sdd_thap_coi)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->dan_toc_chuan_bi_tv)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td text-center">
                @if($student->personMamnon->hoan_thanh_chuong_trinh_gdmn)
                  <span class="text-green-600">✓</span>
                @else
                  —
                @endif
              </td>
              <td class="td">{{ $student->surveyForm->headPerson->full_name ?? '—' }}</td>
              <td class="td">{{ $student->person->addressline ?? '—' }}</td>
              <td class="td">{{ $student->person->village->name ?? '—' }}</td>
              <td class="td">{{ $student->surveyForm->so_phieu ?? '—' }}</td>
              <td class="td">{{ $student->person->contact_name ?? '—' }}</td>
              <td class="td">{{ $student->person->phone ?? '—' }}</td>
              <td class="td">
                @if(auth()->user()->hasPermission('quan_ly_hoc_sinh_mam_non', 'edit'))
                  <a href="{{ route('front.preschool-students.edit', $student->id) }}" 
                     class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 bg-blue-50 rounded hover:bg-blue-100">
                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Sửa
                  </a>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="28" class="td text-center text-gray-500 py-8">
                Không có dữ liệu học sinh mầm non
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <!-- Thông tin phân trang -->
  <div class="mt-4 flex items-center justify-between">
    <div class="text-sm text-gray-600">
      Hiển thị {{ $students->firstItem() ?? 0 }} đến {{ $students->lastItem() ?? 0 }} 
      trong tổng số {{ $students->total() }} học sinh mầm non
    </div>
    
    @if($students->hasPages())
      <div>
        {{ $students->appends(request()->query())->links() }}
      </div>
    @endif
  </div>
</div>


<script>
function toggleGuide() {
    const content = document.getElementById('guide-content');
    const arrow = document.getElementById('guide-arrow');
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        arrow.style.transform = 'rotate(180deg)';
    } else {
        content.classList.add('hidden');
        arrow.style.transform = 'rotate(0deg)';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
    const bulkUpdateStudyBtn = document.getElementById('bulk-update-study-btn');
    const bulkUpdateGdmnBtn = document.getElementById('bulk-update-gdmn-btn');
    const perPageSelect = document.querySelector('select[name="per_page"]');
    

    // Xử lý checkbox "Chọn tất cả"
    selectAllCheckbox.addEventListener('change', function() {
        studentCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateBulkDeleteButton();
        updateBulkUpdateStudyButton();
        updateBulkUpdateGdmnButton();
    });

    // Xử lý checkbox từng dòng
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
            updateBulkDeleteButton();
            updateBulkUpdateStudyButton();
            updateBulkUpdateGdmnButton();
        });
    });

    // Tự động submit form khi thay đổi số dòng hiển thị
    perPageSelect.addEventListener('change', function() {
        this.form.submit();
    });
    

    // Cập nhật trạng thái checkbox "Chọn tất cả"
    function updateSelectAllCheckbox() {
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        const totalCount = studentCheckboxes.length;
        
        if (checkedCount === 0) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = false;
        } else if (checkedCount === totalCount) {
            selectAllCheckbox.indeterminate = false;
            selectAllCheckbox.checked = true;
        } else {
            selectAllCheckbox.indeterminate = true;
            selectAllCheckbox.checked = false;
        }
    }

    // Cập nhật trạng thái nút bulk delete
    function updateBulkDeleteButton() {
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        bulkDeleteBtn.disabled = checkedCount === 0;
        bulkDeleteBtn.textContent = checkedCount > 0 ? `Xóa đã chọn (${checkedCount})` : 'Xóa đã chọn';
    }

    // Cập nhật trạng thái nút bulk update study info
    function updateBulkUpdateStudyButton() {
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        bulkUpdateStudyBtn.disabled = checkedCount === 0;
        bulkUpdateStudyBtn.textContent = checkedCount > 0 ? `Bổ sung thông tin học tập (${checkedCount})` : 'Bổ sung thông tin học tập';
    }

    // Cập nhật trạng thái nút bulk update GDMN
    function updateBulkUpdateGdmnButton() {
        const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
        bulkUpdateGdmnBtn.disabled = checkedCount === 0;
        bulkUpdateGdmnBtn.textContent = checkedCount > 0 ? `Bổ sung hoàn thành GDMN (${checkedCount})` : 'Bổ sung hoàn thành GDMN';
    }

    // Xử lý bulk delete
    bulkDeleteBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        if (checkedBoxes.length === 0) return;

        const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (confirm(`Bạn có chắc chắn muốn xóa ${selectedIds.length} học sinh mầm non đã chọn?`)) {
            // Hiển thị loading
            this.disabled = true;
            this.textContent = 'Đang xóa...';

            fetch('{{ route("front.preschool-students.bulk-delete") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    ids: selectedIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Xóa các dòng đã chọn khỏi bảng
                    checkedBoxes.forEach(checkbox => {
                        checkbox.closest('tr').remove();
                    });
                    
                    // Reset trạng thái
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                    updateBulkDeleteButton();
                    
                    // Hiển thị thông báo thành công
                    alert(data.message);
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi xóa dữ liệu');
            })
            .finally(() => {
                // Reset nút
                this.disabled = false;
                this.textContent = 'Xóa đã chọn';
            });
        }
    });

    // Xử lý bulk update study info
    bulkUpdateStudyBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        if (checkedBoxes.length === 0) return;

        const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (confirm(`Bạn có chắc chắn muốn bổ sung thông tin học tập cho ${selectedIds.length} học sinh mầm non đã chọn?\n\nSẽ tích các trường:\n- Học 2 buổi\n- Học bán trú\n- Học đủ ngày theo quy định\n- Chuyên cần\n\nLưu ý: Học sinh dân tộc thiểu số (khác dân tộc Kinh) sẽ được tích thêm cột "Dân tộc chuẩn bị TV"`)) {
            // Hiển thị loading
            this.disabled = true;
            this.textContent = 'Đang cập nhật...';

            fetch('{{ route("front.preschool-students.bulk-update-study-info") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    ids: selectedIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload trang để hiển thị dữ liệu mới
                    location.reload();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi cập nhật dữ liệu');
            })
            .finally(() => {
                // Reset nút
                this.disabled = false;
                this.textContent = 'Bổ sung thông tin học tập';
            });
        }
    });

    // Xử lý bulk update GDMN
    bulkUpdateGdmnBtn.addEventListener('click', function() {
        const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
        if (checkedBoxes.length === 0) return;

        const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (confirm(`Bạn có chắc chắn muốn bổ sung hoàn thành chương trình GDMN cho ${selectedIds.length} học sinh mầm non đã chọn?\n\nSẽ tích trường:\n- Hoàn thành chương trình GDMN`)) {
            // Hiển thị loading
            this.disabled = true;
            this.textContent = 'Đang cập nhật...';

            fetch('{{ route("front.preschool-students.bulk-update-gdmn") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    ids: selectedIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload trang để hiển thị dữ liệu mới
                    location.reload();
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra khi cập nhật dữ liệu');
            })
            .finally(() => {
                // Reset nút
                this.disabled = false;
                this.textContent = 'Bổ sung hoàn thành GDMN';
            });
        }
    });
});
</script>
@endsection