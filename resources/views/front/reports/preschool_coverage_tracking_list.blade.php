@extends('layouts.front')

@section('title', 'Sổ theo dõi phổ cập giáo dục mầm non cho trẻ em 5 tuổi')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-lg dark:bg-slate-800">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-list mr-2"></i>
                    Sổ theo dõi phổ cập giáo dục mầm non cho trẻ em 5 tuổi
                </h3>
                <a href="{{ route('front.reports.export-1-8', request()->all()) }}" 
                   class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                    <i class="fas fa-file-excel mr-1"></i> Xuất Excel
                </a>
            </div>
        </div>

        <div class="p-6">
            <!-- Thông tin địa bàn -->
            <div class="mb-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 dark:bg-blue-900/20 dark:border-blue-800">
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                        <strong>Năm học:</strong> {{ $year }} | 
                        <strong>Địa bàn:</strong> 
                        {{ $geo['province_name'] ?? '' }}
                        @if($geo['ward_name'] ?? '')
                            → {{ $geo['ward_name'] }}
                        @endif
                    </div>
                </div>
            </div>

            <!-- Bộ lọc -->
            <div class="mb-4">
                <form method="GET" action="{{ route('front.reports.searchreport') }}" class="flex flex-wrap gap-4 items-end">
                                <input type="hidden" name="year" value="{{ $year }}">
                                <input type="hidden" name="geo[province_id]" value="{{ $geo['province_id'] ?? request('geo[province_id]') ?? 1 }}">
                                <input type="hidden" name="geo[district_id]" value="{{ $geo['district_id'] ?? '' }}">
                                <input type="hidden" name="geo[ward_id]" value="{{ $geo['ward_id'] ?? '' }}">
                                <input type="hidden" name="geo[village_id]" value="{{ $geo['village_id'] ?? '' }}">
                                <input type="hidden" name="education[school_id]" value="{{ $schoolId ?? '' }}">
                                <input type="hidden" name="report_group_id" value="{{ $report_group_id }}">
                                <input type="hidden" name="report_detail_id" value="{{ $report_detail_id }}">

                    <div class="flex flex-col">
                        <label for="so_phieu" class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Số phiếu:</label>
                        <input type="text" name="so_phieu" id="so_phieu" 
                               value="{{ $so_phieu }}" 
                               class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white" 
                               placeholder="Nhập số phiếu">
                    </div>

                    <div class="flex flex-col">
                        <label for="q" class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tìm kiếm:</label>
                        <input type="text" name="q" id="q" 
                               value="{{ $q }}" 
                               class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white" 
                               placeholder="Tên trẻ hoặc cha mẹ">
                    </div>

                    <div class="flex flex-col">
                        <label for="per_page" class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hiển thị:</label>
                        <select name="per_page" id="per_page" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white" onchange="this.form.submit()">
                            <option value="20" {{ $per_page == 20 ? 'selected' : '' }}>20</option>
                            <option value="50" {{ $per_page == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $per_page == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>

                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-search mr-1"></i> Tìm kiếm
                    </button>

                    <a href="{{ route('front.reports.searchreport', array_merge(request()->all(), ['so_phieu' => '', 'q' => '', 'per_page' => 20])) }}" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                        <i class="fas fa-refresh mr-1"></i> Làm mới
                    </a>
                </form>
            </div>

            <!-- Bảng dữ liệu -->
            <div class="bg-white rounded-lg border border-gray-200 dark:bg-slate-800 dark:border-slate-700">
                <div class="px-4 py-2 border-b border-gray-200 dark:border-slate-700">
                    <div class="flex justify-between items-center">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Danh sách trẻ em ({{ $rows->total() }} kết quả)
                        </h4>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Cuộn để xem thêm
                        </div>
                    </div>
                </div>
                <div class="overflow-auto max-h-[36rem]">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-800 sticky top-0 z-10">
                            <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">TT</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số phiếu</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Họ và tên</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ngày sinh</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nữ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Chỗ ở</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Dân tộc</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Họ tên cha mẹ</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tên lớp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tên trường</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Khuyết tật/Hoàn cảnh đặc biệt</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-900 dark:divide-slate-700">
                        @forelse($items as $index => $item)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900 dark:text-white">{{ $rows->firstItem() + $index }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $item['so_phieu'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $item['full_name'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $item['birth_date'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900 dark:text-white">{{ $item['is_female'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item['address'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $item['ethnicity'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item['parent_name'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">{{ $item['class_name'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item['school_name'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ $item['special_info'] }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-info-circle mr-2"></i>
                                Không có dữ liệu phù hợp với điều kiện tìm kiếm
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <!-- Phân trang -->
            @if($rows->hasPages())
            <div class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    Hiển thị {{ $rows->firstItem() }} đến {{ $rows->lastItem() }} 
                    trong tổng số {{ $rows->total() }} kết quả
                </div>
                <div>
                    {{ $rows->appends(request()->query())->links() }}
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
