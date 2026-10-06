{{-- Modal form cho KindergartenReport --}}
<div id="schoolDataModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Chỉnh sửa dữ liệu báo cáo mầm non</h3>
                <button type="button" onclick="closeSchoolDataModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="schoolDataForm" class="space-y-4">
                @csrf
                <input type="hidden" name="academic_year" value="{{ $year }}">
                <input type="hidden" name="school_id" id="school_id">
                <input type="hidden" name="ward_id" id="ward_id">
                <input type="hidden" name="school_name" id="school_name">
                <input type="hidden" name="school_rank" id="school_rank">

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {{-- CBQL, giáo viên, nhân viên - Tổng số --}}
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tổng số CBQL, GV, NV</label>
                        <input type="number" name="total_all" id="total_all" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hợp đồng làm việc</label>
                        <input type="number" name="contract_work_all" id="contract_work_all" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hợp đồng lao động - Tổng số</label>
                        <input type="number" name="contract_labor_all" id="contract_labor_all" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Được hưởng CĐ, CS theo quy định</label>
                        <input type="number" name="cs_policy" id="cs_policy" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    {{-- CBQL --}}
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">CBQL - Tổng số</label>
                        <input type="number" name="total_cbql" id="total_cbql" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phó Hiệu trưởng</label>
                        <input type="number" name="vice_principal_cbql" id="vice_principal_cbql" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    {{-- Giáo viên --}}
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Giáo viên - Tổng số</label>
                        <input type="number" name="total_teachers" id="total_teachers" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dân tộc thiểu số</label>
                        <input type="number" name="ethnic_minority" id="ethnic_minority" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tỷ lệ GV/Lớp</label>
                        <input type="number" name="teacher_ratio" id="teacher_ratio" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    {{-- Nhân viên --}}
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nhân viên - Tổng số</label>
                        <input type="number" name="total_staff" id="total_staff" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    {{-- Giáo viên dạy MG 5 tuổi --}}
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">GV dạy MG 5 tuổi - Tổng số</label>
                        <input type="number" name="total_k5" id="total_k5" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hợp đồng làm việc K5</label>
                        <input type="number" name="contract_work_k5" id="contract_work_k5" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hợp đồng lao động K5 - Tổng số</label>
                        <input type="number" name="contract_labor_k5" id="contract_labor_k5" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hợp đồng lao động K5 - Được hưởng CĐ, CS</label>
                        <input type="number" name="cs_policy_k5" id="cs_policy_k5" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tỷ lệ GV/Lớp K5</label>
                        <input type="number" name="teacher_ratio_k5" id="teacher_ratio_k5" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    {{-- Trình độ đào tạo --}}
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Đạt chuẩn</label>
                        <input type="number" name="training_standard" id="training_standard" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Trên chuẩn</label>
                        <input type="number" name="training_above_standard" id="training_above_standard" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>

                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Đạt chuẩn nghề nghiệp</label>
                        <input type="number" name="standard_profession" id="standard_profession" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0">
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" onclick="closeSchoolDataModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Hủy
                    </button>
                    <button type="submit" id="saveData" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Lưu dữ liệu
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
