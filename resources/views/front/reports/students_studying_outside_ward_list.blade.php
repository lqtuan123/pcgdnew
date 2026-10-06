@extends('layouts.front')

@section('title', 'Danh sách học sinh ở địa phương đi học nơi khác')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-lg dark:bg-slate-800">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <i class="fas fa-users mr-2"></i>
                    DANH SÁCH HỌC SINH Ở ĐỊA PHƯƠNG ĐI HỌC NƠI KHÁC
                </h3>
            </div>
        </div>

        <div class="p-6">
            <!-- Thông tin địa bàn -->
            <div class="mb-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 dark:bg-blue-900/20 dark:border-blue-800">
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                        <strong>Năm học:</strong> {{ $year }} | 
                        <strong>Địa chính:</strong> {{ $scheme === 'new' ? 'Mới' : 'Cũ' }} |
                        <strong>Địa bàn:</strong> 
                        {{ $geo['province_name'] ?? '' }}
                        @if(isset($geo['ward_name']) && $geo['ward_name'])
                            → {{ $geo['ward_name'] }}
                        @endif
                    </div>
                </div>
            </div>

            <!-- Form lọc dữ liệu -->
            <form method="GET" action="{{ route('front.reports.searchreport') }}" class="mb-6">
                <input type="hidden" name="report_group_id" value="{{ $report_group_id }}">
                <input type="hidden" name="report_detail_id" value="{{ $report_detail_id }}">
                <input type="hidden" name="geo[province_id]" value="{{ $geo['province_id'] ?? '' }}">
                <input type="hidden" name="geo[ward_id]" value="{{ $geo['ward_id'] ?? '' }}">
                <input type="hidden" name="geo[district_id]" value="{{ $geo['district_id'] ?? '' }}">
                <input type="hidden" name="geo[village_id]" value="{{ $geo['village_id'] ?? '' }}">
                
                <!-- Row 1: Filters chính -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <!-- Năm học -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Năm học <span class="text-red-500">*</span>
                        </label>
                        <select name="year" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white" required>
                            <option value="2023-2024" {{ $year === '2023-2024' ? 'selected' : '' }}>2023-2024</option>
                            <option value="2024-2025" {{ $year === '2024-2025' ? 'selected' : '' }}>2024-2025</option>
                            <option value="2025-2026" {{ $year === '2025-2026' ? 'selected' : '' }}>2025-2026</option>
                        </select>
                    </div>

                    <!-- Địa chính -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Địa chính <span class="text-red-500">*</span>
                        </label>
                        <select name="scheme" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white" required>
                            <option value="old" {{ $scheme === 'old' ? 'selected' : '' }}>Cũ</option>
                            <option value="new" {{ $scheme === 'new' ? 'selected' : '' }}>Mới</option>
                        </select>
                    </div>

                    <!-- Tìm kiếm -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tìm kiếm</label>
                        <input type="text" name="q" value="{{ $q ?? '' }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white" 
                               placeholder="Tên HS, trường...">
                    </div>

                    <!-- Số phiếu -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Số phiếu</label>
                        <input type="text" name="so_phieu" value="{{ $so_phieu ?? '' }}" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white" 
                               placeholder="EK1, TT101...">
                    </div>
                </div>

                <!-- Row 2: Options & Actions -->
                <div class="flex flex-wrap gap-4 items-end">
                    <!-- Số dòng/trang -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hiển thị</label>
                        <select name="per_page" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                            <option value="25" {{ ($per_page ?? 50) == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ ($per_page ?? 50) == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ ($per_page ?? 50) == 100 ? 'selected' : '' }}>100</option>
                            <option value="200" {{ ($per_page ?? 50) == 200 ? 'selected' : '' }}>200</option>
                        </select>
                    </div>

                    <!-- Buttons -->
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-search mr-1"></i> Tìm kiếm
                        </button>
                        
                        <a href="{{ route('front.reports.searchreport', ['report_group_id' => $report_group_id, 'report_detail_id' => $report_detail_id, 'year' => $year, 'scheme' => $scheme, 'geo' => $geo]) }}" 
                           class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-redo mr-1"></i> Làm mới
                        </a>
                        
                        <button type="submit" name="export" value="1" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors">
                            <i class="fas fa-file-excel mr-1"></i> Xuất Excel
                        </button>
                    </div>
                </div>
            </form>

            <!-- Bảng dữ liệu -->
            <div class="bg-white rounded-lg border border-gray-200 dark:bg-slate-800 dark:border-slate-700">
                <div class="px-4 py-2 border-b border-gray-200 dark:border-slate-700">
                    <div class="flex justify-between items-center">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Danh sách học sinh ({{ $rows->total() }} kết quả)
                        </h4>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Trang {{ $rows->currentPage() }}/{{ $rows->lastPage() }}
                        </div>
                    </div>
                </div>
                
                <div class="overflow-auto max-h-[600px]">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-800 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">STT</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Họ và tên</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Ngày sinh</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Nữ</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Dân tộc</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Số phiếu</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Chủ hộ</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Thôn/Xóm</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Khối-Lớp</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Trường học</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Địa chỉ trường</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Hoàn cảnh</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-slate-900 dark:divide-slate-700">
                            @forelse($items as $index => $item)
                            <tr class="hover:bg-gray-50 dark:hover:bg-slate-800">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900 dark:text-white">
                                    {{ $rows->firstItem() + $index }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $item['full_name'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900 dark:text-white">
                                    {{ $item['dob'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900 dark:text-white">
                                    {{ $item['is_female'] ? 'X' : '' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $item['ethnicity'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ $item['so_phieu'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $item['head_name'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    {{ $item['village'] }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-semibold text-blue-600 dark:text-blue-400">
                                    {{ $item['block_name'] }}-{{ $item['class_label'] }}
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $item['school_name'] }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                    @if(!empty($item['school_province']))
                                        <div class="font-medium text-blue-600 dark:text-blue-400">{{ $item['school_province'] }}</div>
                                    @endif
                                    @if(!empty($item['school_district']) || !empty($item['school_ward']))
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            @if(!empty($item['school_district']))
                                                {{ $item['school_district'] }}
                                                @if(!empty($item['school_ward'])) - @endif
                                            @endif
                                            {{ $item['school_ward'] ?? '' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-red-600 dark:text-red-400">
                                    {{ $item['sc_name'] }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="12" class="px-6 py-8 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-inbox fa-3x mb-3 text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-sm font-medium">Không có dữ liệu</p>
                                        <p class="text-xs mt-1">Vui lòng chọn năm học và địa bàn để xem dữ liệu</p>
                                    </div>
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
