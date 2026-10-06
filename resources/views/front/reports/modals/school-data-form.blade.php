{{-- Modal chỉnh sửa dữ liệu cấp trường --}}
<div id="editDataModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
  <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
    {{-- Modal Header --}}
    <div class="flex items-center justify-between pb-4 border-b">
      <h3 class="text-lg font-semibold text-gray-900">Chỉnh sửa dữ liệu báo cáo</h3>
      <button id="closeModal" class="text-gray-400 hover:text-gray-600">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    {{-- Modal Body --}}
    <div class="mt-4 max-h-96 overflow-y-auto">
      <form id="schoolDataForm">
        @csrf
        <input type="hidden" name="academic_year" value="{{ $year }}">
        <input type="hidden" name="school_id" value="{{ $report->school_id ?? '' }}">
        <input type="hidden" name="ward_id" value="{{ $report->ward_id ?? '' }}">
        
        {{-- Form theo đúng thứ tự bảng --}}
        <div class="space-y-6">
          {{-- 2 buổi/ngày --}}
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">2 buổi/ngày</label>
            <input type="number" name="sessions_per_day" value="{{ $report->sessions_per_day ?? 0 }}" 
                   class="w-full border border-gray-300 rounded px-3 py-2" min="0">
          </div>

          {{-- CBQL --}}
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">HT (Hiệu trưởng)</label>
              <input type="number" name="principal" value="{{ $report->principal ?? 0 }}" 
                     class="w-full border border-gray-300 rounded px-3 py-2" min="0">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Phó HT</label>
              <input type="number" name="vice_principal" value="{{ $report->vice_principal ?? 0 }}" 
                     class="w-full border border-gray-300 rounded px-3 py-2" min="0">
            </div>
          </div>

          {{-- Giáo viên - Tổng quát --}}
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số</label>
              <input type="number" name="total_teachers" value="{{ $report->total_teachers ?? 0 }}" 
                     class="w-full border border-gray-300 rounded px-3 py-2" min="0">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Biên chế</label>
              <input type="number" name="teacher_quota" value="{{ $report->teacher_quota ?? 0 }}" 
                     class="w-full border border-gray-300 rounded px-3 py-2" min="0">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Hợp đồng</label>
              <input type="number" name="teacher_contract" value="{{ $report->teacher_contract ?? 0 }}" 
                     class="w-full border border-gray-300 rounded px-3 py-2" min="0">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nữ</label>
              <input type="number" name="female_teachers" value="{{ $report->female_teachers ?? 0 }}" 
                     class="w-full border border-gray-300 rounded px-3 py-2" min="0">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Dân tộc</label>
              <input type="number" name="ethnic_teachers" value="{{ $report->ethnic_teachers ?? 0 }}" 
                     class="w-full border border-gray-300 rounded px-3 py-2" min="0">
            </div>
          </div>

          {{-- Tỷ lệ GV/L --}}
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tỷ lệ GV/L</label>
            <input type="number" name="teacher_student_ratio" value="{{ $report->teacher_student_ratio ?? 0 }}" 
                   class="w-full border border-gray-300 rounded px-3 py-2" step="0.01" min="0">
          </div>

          {{-- Trình độ đào tạo --}}
          <div>
            <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Trình độ đào tạo</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Trên ĐH</label>
                <input type="number" name="training_above_uni" value="{{ $report->training_above_uni ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ĐH</label>
                <input type="number" name="training_uni" value="{{ $report->training_uni ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">CĐ</label>
                <input type="number" name="training_college" value="{{ $report->training_college ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">THSP</label>
                <input type="number" name="training_ped" value="{{ $report->training_ped ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dưới THSP</label>
                <input type="number" name="training_below_ped" value="{{ $report->training_below_ped ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
            </div>
          </div>

          {{-- Loại hình đào tạo --}}
          <div>
            <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Loại hình đào tạo</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tiểu học</label>
                <input type="number" name="training_primary" value="{{ $report->training_primary ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">AN</label>
                <input type="number" name="training_an" value="{{ $report->training_an ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">MT</label>
                <input type="number" name="training_mt" value="{{ $report->training_mt ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">TD</label>
                <input type="number" name="training_td" value="{{ $report->training_td ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tin học</label>
                <input type="number" name="training_informatics" value="{{ $report->training_informatics ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">NN</label>
                <input type="number" name="training_foreign_lang" value="{{ $report->training_foreign_lang ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Khác</label>
                <input type="number" name="training_other" value="{{ $report->training_other ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
            </div>
          </div>

          {{-- Chuẩn nghề nghiệp --}}
          <div>
            <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Chuẩn nghề nghiệp</h4>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tốt</label>
                <input type="number" name="standard_good" value="{{ $report->standard_good ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Khá</label>
                <input type="number" name="standard_quite" value="{{ $report->standard_quite ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Đạt</label>
                <input type="number" name="standard_reach" value="{{ $report->standard_reach ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Chưa đạt</label>
                <input type="number" name="standard_not_reach" value="{{ $report->standard_not_reach ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
            </div>
          </div>

          {{-- Nhân viên --}}
          <div>
            <h4 class="font-semibold text-gray-800 border-b pb-2 mb-4">Nhân viên</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">TPT Đội</label>
                <input type="number" name="tpt_team" value="{{ $report->tpt_team ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Văn phòng</label>
                <input type="number" name="office_staff" value="{{ $report->office_staff ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">T.Viện - TBDH</label>
                <input type="number" name="library_other_staff" value="{{ $report->library_other_staff ?? 0 }}" 
                       class="w-full border border-gray-300 rounded px-3 py-2" min="0">
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="mt-6 flex justify-end space-x-3 pt-6 border-t">
          <button type="button" id="resetForm" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
            Làm mới
          </button>
          <button type="button" id="closeModalBtn" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
            Hủy
          </button>
          <button type="submit" id="saveData" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Lưu dữ liệu
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
