<?php

namespace App\Services;

use App\Models\ReportGroup;
use App\Models\ReportDetail;
use Illuminate\Support\Collection;

class ReportManagementService
{
    /**
     * Lấy tất cả nhóm báo cáo với chi tiết
     */
    public function getAllReportGroups(): Collection
    {
        return ReportGroup::active()
            ->ordered()
            ->with(['reportDetails' => function ($query) {
                $query->active()->ordered();
            }])
            ->get();
    }

    /**
     * Lấy nhóm báo cáo theo ID
     */
    public function getReportGroupById(int $id): ?ReportGroup
    {
        return ReportGroup::with(['reportDetails' => function ($query) {
            $query->active()->ordered();
        }])->find($id);
    }

    /**
     * Tạo nhóm báo cáo mới
     */
    public function createReportGroup(array $data): ReportGroup
    {
        return ReportGroup::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Cập nhật nhóm báo cáo
     */
    public function updateReportGroup(int $id, array $data): ReportGroup
    {
        $reportGroup = ReportGroup::findOrFail($id);
        
        $reportGroup->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? $reportGroup->description,
            'sort_order' => $data['sort_order'] ?? $reportGroup->sort_order,
            'is_active' => $data['is_active'] ?? $reportGroup->is_active,
        ]);

        return $reportGroup;
    }

    /**
     * Xóa nhóm báo cáo và tất cả báo cáo chi tiết
     */
    public function deleteReportGroup(int $id): bool
    {
        $reportGroup = ReportGroup::findOrFail($id);
        
        // Xóa tất cả báo cáo chi tiết thuộc nhóm này
        $reportGroup->reportDetails()->delete();
        
        // Xóa nhóm báo cáo
        return $reportGroup->delete();
    }

    /**
     * Tạo báo cáo chi tiết mới
     */
    public function createReportDetail(array $data): ReportDetail
    {
        return ReportDetail::create([
            'report_group_id' => $data['report_group_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'value' => $data['value'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Cập nhật báo cáo chi tiết
     */
    public function updateReportDetail(int $id, array $data): ReportDetail
    {
        $reportDetail = ReportDetail::findOrFail($id);
        
        $reportDetail->update([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? $reportDetail->description,
            'value' => $data['value'] ?? $reportDetail->value,
            'sort_order' => $data['sort_order'] ?? $reportDetail->sort_order,
            'is_active' => $data['is_active'] ?? $reportDetail->is_active,
        ]);

        return $reportDetail;
    }

    /**
     * Xóa báo cáo chi tiết
     */
    public function deleteReportDetail(int $id): bool
    {
        $reportDetail = ReportDetail::findOrFail($id);
        return $reportDetail->delete();
    }

    /**
     * Lấy báo cáo chi tiết theo nhóm
     */
    public function getReportDetailsByGroup(int $groupId): Collection
    {
        return ReportDetail::byGroup($groupId)
            ->active()
            ->ordered()
            ->with('reportGroup')
            ->get();
    }

    /**
     * Cập nhật giá trị báo cáo
     */
    public function updateReportValue(int $id, string $value): ReportDetail
    {
        $reportDetail = ReportDetail::findOrFail($id);
        $reportDetail->update(['value' => $value]);
        return $reportDetail;
    }

    /**
     * Lấy báo cáo theo mã code
     */
    public function getReportByCode(string $code): ?ReportDetail
    {
        return ReportDetail::where('code', $code)->active()->first();
    }

    /**
     * Lấy nhóm báo cáo theo mã code
     */
    public function getReportGroupByCode(string $code): ?ReportGroup
    {
        return ReportGroup::where('code', $code)->active()->first();
    }

    /**
     * Tìm kiếm báo cáo theo từ khóa
     */
    public function searchReports(string $keyword): Collection
    {
        return ReportDetail::where(function ($query) use ($keyword) {
            $query->where('name', 'like', "%{$keyword}%")
                  ->orWhere('code', 'like', "%{$keyword}%")
                  ->orWhere('description', 'like', "%{$keyword}%");
        })
        ->active()
        ->with('reportGroup')
        ->get();
    }

    /**
     * Lấy thống kê tổng quan
     */
    public function getReportStatistics(): array
    {
        $totalGroups = ReportGroup::active()->count();
        $totalDetails = ReportDetail::active()->count();
        $groupsWithDetails = ReportGroup::active()
            ->whereHas('reportDetails')
            ->count();

        return [
            'total_groups' => $totalGroups,
            'total_details' => $totalDetails,
            'groups_with_details' => $groupsWithDetails,
            'groups_without_details' => $totalGroups - $groupsWithDetails,
        ];
    }

    /**
     * Cập nhật thứ tự sắp xếp cho nhóm báo cáo
     */
    public function updateGroupSortOrder(array $groupOrders): bool
    {
        foreach ($groupOrders as $groupId => $sortOrder) {
            ReportGroup::where('id', $groupId)->update(['sort_order' => $sortOrder]);
        }
        return true;
    }

    /**
     * Cập nhật thứ tự sắp xếp cho báo cáo chi tiết
     */
    public function updateDetailSortOrder(array $detailOrders): bool
    {
        foreach ($detailOrders as $detailId => $sortOrder) {
            ReportDetail::where('id', $detailId)->update(['sort_order' => $sortOrder]);
        }
        return true;
    }

    /**
     * Kích hoạt/vô hiệu hóa nhóm báo cáo
     */
    public function toggleGroupStatus(int $id): ReportGroup
    {
        $reportGroup = ReportGroup::findOrFail($id);
        $reportGroup->update(['is_active' => !$reportGroup->is_active]);
        return $reportGroup;
    }

    /**
     * Kích hoạt/vô hiệu hóa báo cáo chi tiết
     */
    public function toggleDetailStatus(int $id): ReportDetail
    {
        $reportDetail = ReportDetail::findOrFail($id);
        $reportDetail->update(['is_active' => !$reportDetail->is_active]);
        return $reportDetail;
    }
}
