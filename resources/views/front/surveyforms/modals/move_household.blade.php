<div id="move-household-modal" class="grid gap-4" data-config='{
    "surveyId": {{ $survey->id }},
    "academicYear": "{{ $academicYear }}",
    "villagesUrl": "{{ route('front.surveyforms.move.villages') }}",
    "submitUrl": "{{ route('front.surveyforms.move.store', $survey) }}"
}' data-members='{{ json_encode($members->map(function($member) {
    return [
        "person_id" => $member["person_id"],
        "relationship" => $member["relationship"]
    ];
})) }}'>
    <div class="text-sm text-slate-600 mb-3">
        <div><b>Phiếu gốc:</b> {{ $survey->so_phieu }} (Năm: {{ $survey->academic_year }})</div>
        <div><b>Năm học chuyển hộ:</b> {{ $academicYear }}</div>
        <div>Chọn thôn/xóm mới và nhập thông tin hộ gia đình mới.</div>
    </div>

    <!-- Thông tin hộ mới -->
    <div id="move-household-info" class="grid gap-4 p-4 bg-slate-50 rounded-lg">
        <h3 class="font-semibold text-slate-700">Thông tin hộ gia đình mới</h3>
        
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Số phiếu mới *</label>
                <input type="text" id="move-new-so-phieu" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500" placeholder="Nhập số phiếu mới">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Tên chủ hộ mới *</label>
                <input type="text" id="move-new-head-name" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-gray-100" value="{{ $currentHeadName }}" readonly>
                <p class="text-xs text-gray-500 mt-1">Tên chủ hộ được giữ nguyên từ hộ cũ</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Xã/Phường (địa giới mới) *</label>
                <select id="move-ward-select" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500">
                    <option value="">Chọn xã/phường</option>
                    @if($survey->newWard)
                        <option value="{{ $survey->newWard->id }}" selected>{{ $survey->newWard->name }}</option>
                    @endif
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Thôn/Xóm *</label>
                <select id="move-village-select" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500">
                    <option value="">Chọn thôn/xóm</option>
                    @foreach($villages as $village)
                        <option value="{{ $village->id }}">{{ $village->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Thông báo chuyển hộ -->
    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
            </svg>
            <div>
                <h4 class="font-medium text-blue-800">Thông báo chuyển hộ</h4>
                <p class="text-sm text-blue-700 mt-1">Tất cả thành viên của năm học {{ $academicYear }} sẽ được chuyển sang hộ mới. Hộ cũ sẽ bị khóa sau khi chuyển.</p>
            </div>
        </div>
    </div>

    <!-- Nút thao tác -->
    <div class="mt-2 flex items-center justify-end gap-2">
        <button type="button" class="rounded-lg border px-3 py-2 hover:bg-slate-50" data-close-modal>Đóng</button>
        <button type="button" id="move-submit-btn" class="rounded-lg bg-cyan-600 px-3 py-2 text-white hover:bg-cyan-700">Chuyển hộ & Lưu</button>
    </div>
</div>

