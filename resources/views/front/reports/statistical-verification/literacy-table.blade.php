{{-- Bảng Xóa mù chữ --}}
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
        <thead class="bg-green-50">
            <tr>
                <th rowspan="2" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300 align-middle">TT</th>
                <th rowspan="2" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300 align-middle min-w-[300px]">Tiêu chí</th>
                <th colspan="6" class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border border-gray-300">Chỉ tiêu</th>
            </tr>
            <tr>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-green-100">XMC (5.1)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-green-100">XMC (5.2)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-green-100">XMC (5.3)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-green-100">XMC (5.5)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-green-100">XMC (5.6)</th>
                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-700 border border-gray-300 bg-green-100">XMC (5.9)</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @php
            $literacyCriterias = [
                ['id' => 1, 'name' => 'Dân số từ 15-25 tuổi'],
                ['id' => 2, 'name' => 'Dân số từ 15-35 tuổi'],
                ['id' => 3, 'name' => 'Dân số từ 15-60 tuổi'],
                ['id' => 4, 'name' => 'Dân số từ 15-25 tuổi mù chữ mức độ 1'],
                ['id' => 5, 'name' => 'Dân số từ 15-35 tuổi mù chữ mức độ 1'],
                ['id' => 6, 'name' => 'Dân số từ 15-60 tuổi mù chữ mức độ 1'],
                ['id' => 7, 'name' => 'Dân số từ 15-25 tuổi mù chữ mức độ 2'],
                ['id' => 8, 'name' => 'Dân số từ 15-35 tuổi mù chữ mức độ 2'],
                ['id' => 9, 'name' => 'Dân số từ 15-60 tuổi mù chữ mức độ 2'],
            ];
            @endphp

            @foreach($literacyCriterias as $criteria)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">{{ $criteria['id'] }}</td>
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300">{{ $criteria['name'] }}</td>

                {{-- XMC (5.1): tiêu chí 1..9 theo controller --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php $vx = isset($xmc51) ? ($xmc51[$criteria['id']] ?? null) : null; @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ is_null($vx) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ is_null($vx) ? '' : $vx }}" readonly disabled>
                </td>

                {{-- XMC (5.2): tiêu chí 1..9 từ LiteracyOutcomeReport --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php $v52 = isset($xmc52) ? ($xmc52[$criteria['id']] ?? null) : null; @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ is_null($v52) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ is_null($v52) ? '' : $v52 }}" readonly disabled>
                </td>

                {{-- XMC (5.3): tiêu chí 1..9 từ IlliteracyByAgeBandsReport --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php $v53 = isset($xmc53) ? ($xmc53[$criteria['id']] ?? null) : null; @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ is_null($v53) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700' }}" value="{{ is_null($v53) ? '' : $v53 }}" readonly disabled>
                </td>

                {{-- XMC (5.5): tiêu chí 1..6 từ AntiIlliteracyLevel1Report --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php 
                        $v55 = isset($xmc55) ? ($xmc55[$criteria['id']] ?? null) : null;
                        // TT 7-9 không có dữ liệu, bôi xám
                        $is55Grey = $criteria['id'] >= 7;
                    @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ $is55Grey ? 'bg-slate-200 text-slate-500' : (is_null($v55) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700') }}" value="{{ $is55Grey ? '' : (is_null($v55) ? '' : $v55) }}" readonly disabled>
                </td>

                {{-- XMC (5.6): tiêu chí 1-3 và 7-9 từ AntiIlliteracyLevel2Report (bỏ 4-6) --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php 
                        $v56 = isset($xmc56) ? ($xmc56[$criteria['id']] ?? null) : null;
                        // TT 4-6 không có dữ liệu, bôi xám
                        $is56Grey = $criteria['id'] >= 4 && $criteria['id'] <= 6;
                    @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ $is56Grey ? 'bg-slate-200 text-slate-500' : (is_null($v56) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700') }}" value="{{ $is56Grey ? '' : (is_null($v56) ? '' : $v56) }}" readonly disabled>
                </td>

                {{-- XMC (5.9): tiêu chí 1-3 từ LiteracyOutcomeSummaryReport (chỉ tổng dân số) --}}
                <td class="px-4 py-3 text-sm text-gray-900 border border-gray-300 text-center">
                    @php 
                        $v59 = isset($xmc59) ? ($xmc59[$criteria['id']] ?? null) : null;
                        // TT 4-9 không có dữ liệu, bôi xám
                        $is59Grey = $criteria['id'] >= 4;
                    @endphp
                    <input type="text" class="w-full px-2 py-1 border border-gray-200 rounded {{ $is59Grey ? 'bg-slate-200 text-slate-500' : (is_null($v59) ? 'bg-slate-200 text-slate-500' : 'bg-gray-50 text-gray-700') }}" value="{{ $is59Grey ? '' : (is_null($v59) ? '' : $v59) }}" readonly disabled>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

