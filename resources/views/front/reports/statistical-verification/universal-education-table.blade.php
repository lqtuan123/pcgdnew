{{-- Bảng Phổ cập giáo dục --}}
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
        <thead class="bg-blue-50">
            <tr>
                <th rowspan="2" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300 align-middle">
                    TT
                </th>
                <th rowspan="2" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300 align-middle min-w-[300px]">
                    Tiêu chí
                </th>
                <th colspan="6" class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">
                    Chỉ tiêu
                </th>
            </tr>
            <tr>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-blue-100">Tiểu học (2.1)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-blue-100">THCS (3.1)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-blue-100">THCS (3.2)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-blue-100">THPT (4.1)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-blue-100">THPT (4.2)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-blue-100">THPT (4.3)</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @php
            $criterias = [
                ['id' => 1, 'name' => 'Trẻ 6 tuổi'],
                ['id' => 2, 'name' => 'Trẻ 6 tuổi học lớp 1'],
                ['id' => 3, 'name' => 'Số PPC 11 tuổi'],
                ['id' => 4, 'name' => 'Số PPC 12 tuổi'],
                ['id' => 5, 'name' => 'Số PPC 13 tuổi'],
                ['id' => 6, 'name' => 'Số PPC 14 tuổi'],
                ['id' => 7, 'name' => 'Số đối tượng PPC 11-14 tuổi'],
                ['id' => 8, 'name' => '11 tuổi hoàn thành TiH'],
                ['id' => 9, 'name' => '11-14 tuổi hoàn thành TiH'],
                ['id' => 10, 'name' => 'Số đối tượng PPC 15-18 tuổi'],
                ['id' => 11, 'name' => 'Tổng số 15-18 tuổi tốt nghiệp THCS'],
                ['id' => 12, 'name' => 'Số đối tượng PPC 15-21 tuổi'],
                ['id' => 13, 'name' => 'Số đối tượng PPC 18-21 tuổi'],
                // ['id' => 14, 'name' => '15-18 tuổi tốt nghiệp THCS năm qua vào lớp 10 PT'],
                // ['id' => 15, 'name' => '15-18 tuổi tốt nghiệp THCS năm qua vào lớp 10 BT'],
                // ['id' => 16, 'name' => '18-21 tuổi tốt nghiệp THPT PT'],
                // ['id' => 17, 'name' => '18-21 tuổi tốt nghiệp THPT BT'],
                // ['id' => 18, 'name' => 'Tốt nghiệp THPT năm qua'],
                // ['id' => 19, 'name' => 'Tốt nghiệp THPT BT năm qua'],
                // ['id' => 20, 'name' => '18-21 tuổi có bằng đào tạo nghề'],
                // ['id' => 21, 'name' => 'Học sinh lớp 9 năm qua (2 hệ)'],
            ];
            @endphp

            @foreach($criterias as $criteria)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">{{ $criteria['id'] }}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300">{{ $criteria['name'] }}</td>
                {{-- Cột Tiểu học (2.1) hiển thị dữ liệu thật cho chỉ tiêu 1..9 --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php
                        $id = $criteria['id'];
                        $isGrey = $id >= 10; // từ 10 trở đi để null và bôi xám
                        $val21 = $isGrey ? '' : (isset($pcgd21) ? ($pcgd21[$id] ?? 0) : 0);
                    @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ $isGrey ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ $val21 }}" readonly disabled>
                </td>
                {{-- THCS (3.1) lấy dữ liệu cho chỉ tiêu 3..7 và 10..14, còn lại bôi xám --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php $v = isset($thcs31) ? ($thcs31[$criteria['id']] ?? null) : null; @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ is_null($v) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ is_null($v) ? '' : $v }}" readonly disabled>
                </td>

                {{-- THCS (3.2) lấy theo R32 cho các chỉ tiêu 1,2,7,9..13,15,16; còn lại bôi xám --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php $v32 = isset($thcs32) ? ($thcs32[$criteria['id']] ?? null) : null; @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ is_null($v32) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ is_null($v32) ? '' : $v32 }}" readonly disabled>
                </td>

                {{-- THPT (4.1) lấy theo R41 cho các chỉ tiêu 6,10,13,15,17..25; còn lại bôi xám --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php $v41 = isset($thpt41) ? ($thpt41[$criteria['id']] ?? null) : null; @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ is_null($v41) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ is_null($v41) ? '' : $v41 }}" readonly disabled>
                </td>
                {{-- THPT (4.2) lấy theo R42 cho các chỉ tiêu 10,13,15,16,17,18,19,26; còn lại bôi xám --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php $v42 = isset($thpt42) ? ($thpt42[$criteria['id']] ?? null) : null; @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ is_null($v42) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ is_null($v42) ? '' : $v42 }}" readonly disabled>
                </td>
                {{-- THPT (4.3) khóa --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php $v43 = isset($thpt43) ? ($thpt43[$criteria['id']] ?? null) : null; @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ is_null($v43) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ is_null($v43) ? '' : $v43 }}" readonly disabled>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

