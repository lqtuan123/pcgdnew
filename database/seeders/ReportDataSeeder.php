<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ReportGroup;
use App\Models\ReportDetail;

class ReportDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo các nhóm báo cáo
        $reportGroups = [
            [
                'name' => 'Mầm non',
                'code' => 'mam_non',
                'description' => 'Báo cáo thống kê giáo dục mầm non',
                'sort_order' => 1,
            ],
            [
                'name' => 'Tiểu học',
                'code' => 'tieu_hoc',
                'description' => 'Báo cáo thống kê giáo dục tiểu học',
                'sort_order' => 2,
            ],
            [
                'name' => 'Trung học cơ sở',
                'code' => 'trung_hoc_co_so',
                'description' => 'Báo cáo thống kê giáo dục trung học cơ sở',
                'sort_order' => 3,
            ],
            [
                'name' => 'Trung học phổ thông',
                'code' => 'trung_hoc_pho_thong',
                'description' => 'Báo cáo thống kê giáo dục trung học phổ thông',
                'sort_order' => 4,
            ],
            [
                'name' => 'Xóa mù chữ',
                'code' => 'xoa_mu_chu',
                'description' => 'Báo cáo thống kê công tác xóa mù chữ',
                'sort_order' => 5,
            ],
            [
                'name' => 'Thống kê khác',
                'code' => 'thong_ke_khac',
                'description' => 'Các báo cáo thống kê khác',
                'sort_order' => 6,
            ],
        ];

        foreach ($reportGroups as $groupData) {
            $group = ReportGroup::create($groupData);
            
            // Tạo báo cáo chi tiết cho từng nhóm
            $this->createReportDetails($group);
        }
    }

    private function createReportDetails(ReportGroup $group)
    {
        $details = [];

        switch ($group->code) {
            case 'mam_non':
                $details = [
                    ['name' => 'Thống kê trẻ từ 0-5 tuổi', 'code' => 'mam_non_1_1'],
                    ['name' => 'Thống kê trẻ từ 0-5 tuổi cũ', 'code' => 'mam_non_1_2'],
                    ['name' => 'Thống kê tiêu chuẩn phòng học, cơ sở vật chất', 'code' => 'mam_non_1_3'],
                    ['name' => 'Thống kê đội ngũ cán bộ quản lý, giáo viên và nhân viên', 'code' => 'mam_non_1_4'],
                    ['name' => 'Báo cáo tài chính', 'code' => 'mam_non_1_5'],
                    ['name' => 'Khuyết tật mầm non', 'code' => 'mam_non_1_6'],
                    ['name' => 'Danh sách học sinh các phường xã đang học tại trường', 'code' => 'mam_non_1_7'],
                    ['name' => 'Sổ theo dõi phổ cập giáo dục mầm non cho trẻ em 5 tuổi', 'code' => 'mam_non_1_8'],
                    ['name' => 'Thống kê trẻ học trường công lập và ngoài công lập', 'code' => 'mam_non_1_9'],
                    ['name' => 'Thống kê trẻ 4 tuổi', 'code' => 'mam_non_1_10'],
                ];
                break;

            case 'tieu_hoc':
                $details = [
                    ['name' => 'Thống kê trẻ em 6-14 tuổi', 'code' => 'tieu_hoc_2_1'],
                    ['name' => 'Thống kê cơ sở vật chất giáo dục tiểu học', 'code' => 'tieu_hoc_2_2'],
                    ['name' => 'Thống kê đội ngũ giáo viên tiểu học', 'code' => 'tieu_hoc_2_3'],
                    ['name' => 'Thống kê học sinh đang học trường ngoài phường xã', 'code' => 'tieu_hoc_2_4'],
                    ['name' => 'Phân tích số liệu học sinh trái tuyến đang học tại trường', 'code' => 'tieu_hoc_2_5'],
                    ['name' => 'Danh sách học sinh các phường xã đang học tại trường', 'code' => 'tieu_hoc_2_6'],
                    ['name' => 'Thống kê khuyết tật tiểu học', 'code' => 'tieu_hoc_2_7'],
                    ['name' => 'Danh sách học sinh khuyết tật tiểu học', 'code' => 'tieu_hoc_2_8'],
                    ['name' => 'Danh sách trẻ trong độ tuổi 6-14 chưa đi học và bỏ học', 'code' => 'tieu_hoc_2_9'],
                    ['name' => 'Sổ phổ cập giáo dục tiểu học', 'code' => 'tieu_hoc_2_10'],
                ];
                break;

            case 'trung_hoc_co_so':
                $details = [
                    ['name' => 'Thống kê thanh thiếu niên phổ cập giáo dục THCS', 'code' => 'thcs_3_1'],
                    ['name' => 'Kết quả huy động PCGD', 'code' => 'thcs_3_2'],
                    ['name' => 'Thống kê tình hình cơ sở vật chất cho dạy và học', 'code' => 'thcs_3_3'],
                    ['name' => 'Thống kê tình hình đội ngũ giáo viên THCS', 'code' => 'thcs_3_4'],
                    ['name' => 'Thống kê đội ngũ quản lý, giáo viên THCS', 'code' => 'thcs_3_5'],
                    ['name' => 'Phân tích số liệu học sinh đang học ngoài phường xã', 'code' => 'thcs_3_6'],
                    ['name' => 'Phân tích số liệu học sinh ngoài phường xã đang học tại trường', 'code' => 'thcs_3_7'],
                    ['name' => 'Danh sách học sinh các phường xã đang học tại trường', 'code' => 'thcs_3_8'],
                    ['name' => 'Thống kê khuyết tật THCS', 'code' => 'thcs_3_9'],
                    ['name' => 'Sổ phổ cập THCS', 'code' => 'thcs_3_10'],
                ];
                break;

            case 'trung_hoc_pho_thong':
                $details = [
                    ['name' => 'Tổng hợp kết quả điều tra phổ cập bậc trung học', 'code' => 'thpt_4_1'],
                    ['name' => 'Tổng hợp kết quả huy động phổ cập bậc trung học', 'code' => 'thpt_4_2'],
                    ['name' => 'Tổng hợp hiệu quả phổ cập bậc trung học', 'code' => 'thpt_4_3'],
                    ['name' => 'Tổng hợp trường phổ thông, trung tâm, cơ sở dạy nghề', 'code' => 'thpt_4_4'],
                    ['name' => 'Thống kê khuyết tật THPT', 'code' => 'thpt_4_5'],
                    ['name' => 'Sổ phổ cập THPT', 'code' => 'thpt_4_6'],
                ];
                break;

            case 'xoa_mu_chu':
                $details = [
                    ['name' => 'Tổng hợp tình hình số liệu công tác xóa mù chữ', 'code' => 'xmc_5_1'],
                    ['name' => 'Tổng hợp kết quả xóa mù chữ', 'code' => 'xmc_5_2'],
                    ['name' => 'Thống kê số người mù chữ theo các độ tuổi', 'code' => 'xmc_5_3'],
                    ['name' => 'Thống kê danh sách người mù chữ', 'code' => 'xmc_5_4'],
                    ['name' => 'Thống kê hiện trạng mù chữ mức 1', 'code' => 'xmc_5_5'],
                    ['name' => 'Thống kê hiện trạng mù chữ mức 2', 'code' => 'xmc_5_6'],
                    ['name' => 'Thống kê kết quả học xóa mù chữ mức 1', 'code' => 'xmc_5_7'],
                    ['name' => 'Thống kê kết quả học xóa mù chữ mức 2', 'code' => 'xmc_5_8'],
                    ['name' => 'Thống kê kết quả xóa mù chữ', 'code' => 'xmc_5_9'],
                    ['name' => 'Thống kê trình độ văn hóa', 'code' => 'xmc_5_10'],
                ];
                break;

            case 'thong_ke_khac':
                $details = [
                    ['name' => 'Danh sách học sinh tốt nghiệp năm qua', 'code' => 'tk_6_1'],
                    ['name' => 'Danh sách đối tượng 15-18 tuổi tốt nghiệp THCS', 'code' => 'tk_6_2'],
                    ['name' => 'Danh sách đối tượng khuyết tật', 'code' => 'tk_6_3'],
                    ['name' => 'Danh sách đối tượng bỏ học', 'code' => 'tk_6_4'],
                    ['name' => 'Danh sách đối tượng chuyển đi', 'code' => 'tk_6_5'],
                    ['name' => 'Danh sách đối tượng đã chết', 'code' => 'tk_6_6'],
                    ['name' => 'Thống kê dân tộc thiểu số', 'code' => 'tk_6_7'],
                    ['name' => 'Danh sách học sinh ở địa phương đi học nơi khác', 'code' => 'tk_6_8'],
                    ['name' => 'Danh sách học sinh tốt nghiệp tiểu học trong độ tuổi PCGD 11-14', 'code' => 'tk_6_9'],
                    ['name' => 'Danh sách đối tượng chuyển đến', 'code' => 'tk_6_10'],
                    ['name' => 'Thống kê trình độ văn hóa dân tộc thiểu số', 'code' => 'tk_6_11'],
                    ['name' => 'Thống kê trình độ văn hóa đối tượng nữ', 'code' => 'tk_6_12'],
                    ['name' => 'Danh sách học sinh tốt nghiệp 5 năm qua', 'code' => 'tk_6_13'],
                    ['name' => 'Thống kê XMC và GD tiếu tục sau biết chữ', 'code' => 'tk_6_14'],
                    ['name' => 'Tổng hợp số liệu công tác xóa mù chữ', 'code' => 'tk_6_15'],
                    ['name' => 'Thống kê số người mù chữ theo dân tộc', 'code' => 'tk_6_16'],
                    ['name' => 'Danh sách đối tượng 15-18 tuổi TN THCS tiếp tục học THPT', 'code' => 'tk_6_17'],
                    ['name' => 'Danh sách TN THCS năm qua tiếp tục học THPT', 'code' => 'tk_6_18'],
                    ['name' => 'Danh sách lưu ban', 'code' => 'tk_6_19'],
                ];
                break;
        }

        foreach ($details as $index => $detailData) {
            ReportDetail::create([
                'report_group_id' => $group->id,
                'name' => $detailData['name'],
                'code' => $detailData['code'],
                'description' => "Báo cáo {$detailData['name']}",
                'sort_order' => $index + 1,
            ]);
        }
    }
}
