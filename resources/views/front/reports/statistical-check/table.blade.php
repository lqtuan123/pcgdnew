{{-- Bảng kiểm tra thống kê --}}
<div class="overflow-x-auto">
    <table class="w-full border-collapse">
        <thead>
            <tr class="bg-gray-50">
                <th class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 text-center w-12">TT</th>
                <th class="border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 text-left min-w-[300px]">Tiêu chí</th>
                @foreach($years as $yearData)
                    <th class="border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 text-center min-w-[80px]">
                        <div class="font-bold">{{ $yearData['year'] }}</div>
                        <div class="text-xs font-normal text-gray-600">{{ $yearData['age'] }} tuổi</div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($criteria as $index => $criterion)
                <tr class="hover:bg-gray-50">
                    <td class="border border-gray-300 px-3 py-2 text-sm text-center font-medium">{{ $index }}</td>
                    <td class="border border-gray-300 px-4 py-2 text-sm">{{ $criterion }}</td>
                    @foreach($years as $yearData)
                        @php
                            $value = $data[$index][$yearData['year']] ?? null;
                            $source = $sources[$index][$yearData['year']] ?? null;
                            
                            // Xác định ô nào nên bôi xám (không có dữ liệu theo logic nghiệp vụ)
                            $isGrayed = false;
                            
                            // Tiêu chí 1: PPC (6-18 tuổi)
                            if ($index == 1 && ($yearData['age'] < 6 || $yearData['age'] > 18)) {
                                $isGrayed = true;
                            }
                            // Tiêu chí 2-3: Đang học TH và HTCTTH (6-14 tuổi)
                            if (in_array($index, [2,3]) && ($yearData['age'] < 6 || $yearData['age'] > 14)) {
                                $isGrayed = true;
                            }
                            // Tiêu chí 4-5: THCS (11-18 tuổi)
                            if (in_array($index, [4,5]) && ($yearData['age'] < 11 || $yearData['age'] > 18)) {
                                $isGrayed = true;
                            }
                            // Tiêu chí 6: THPT/GDNN (15-18 tuổi)
                            if ($index == 6 && ($yearData['age'] < 15 || $yearData['age'] > 18)) {
                                $isGrayed = true;
                            }
                            // Tiêu chí 7: Bỏ học TH (6-14 tuổi)
                            if ($index == 7 && ($yearData['age'] < 6 || $yearData['age'] > 14)) {
                                $isGrayed = true;
                            }
                            // Tiêu chí 8: Bỏ học THCS (11-18 tuổi)
                            if ($index == 8 && ($yearData['age'] < 11 || $yearData['age'] > 18)) {
                                $isGrayed = true;
                            }
                            // Tiêu chí 9: Bỏ học THPT (15-18 tuổi)
                            if ($index == 9 && ($yearData['age'] < 15 || $yearData['age'] > 18)) {
                                $isGrayed = true;
                            }
                            // Tiêu chí 10: Chưa đi học (6-14 tuổi)
                            if ($index == 10 && ($yearData['age'] < 6 || $yearData['age'] > 14)) {
                                $isGrayed = true;
                            }
                        @endphp
                        <td class="border border-gray-300 px-3 py-2 text-sm text-center {{ $isGrayed ? 'bg-gray-200' : '' }}" 
                            @if(!$isGrayed && $source) title="{{ $source }}" @endif>
                            @if(!$isGrayed)
                                <span class="{{ $source ? 'cursor-help' : '' }}">
                                    {{ $value !== null ? number_format($value) : '' }}
                                </span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            
            {{-- Dòng kết quả kiểm tra --}}
            {{-- <tr class="bg-yellow-50 font-semibold">
                <td class="border border-gray-300 px-3 py-2 text-sm text-center" colspan="2">
                    <span class="font-bold">KẾT QUẢ KIỂM TRA</span>
                </td>
                @foreach($years as $yearData)
                    @php
                        // TODO: Tính toán kết quả kiểm tra dựa vào logic nghiệp vụ
                        // Hiện tại để mặc định là chưa kiểm tra (màu xám)
                        $result = $data['result'][$yearData['year']] ?? null;
                        
                        // Xác định màu icon dựa vào kết quả
                        $iconColor = 'text-gray-400'; // Mặc định: chưa kiểm tra
                        $iconLabel = '○';
                        
                        if ($result === 'pass') {
                            $iconColor = 'text-green-500'; // Đạt
                            $iconLabel = '✓';
                        } elseif ($result === 'warning') {
                            $iconColor = 'text-yellow-500'; // Cảnh báo
                            $iconLabel = '◐';
                        } elseif ($result === 'fail') {
                            $iconColor = 'text-red-500'; // Không đạt
                            $iconLabel = '✗';
                        }
                    @endphp
                    <td class="border border-gray-300 px-3 py-2 text-center">
                        <span class="text-2xl {{ $iconColor }}">{{ $iconLabel }}</span>
                    </td>
                @endforeach
            </tr> --}}
        </tbody>
    </table>
</div>

{{-- Chú thích --}}


