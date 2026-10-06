<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemFunction;
use App\Models\UserRole;
use App\Models\RoleFunctionPermission;

class ReportPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tạo các chức năng làm báo cáo theo danh mục cụ thể
        $reportFunctions = [
            // 1. Mầm non
            [
                'code' => 'thong_ke_tre_0_5_tuoi',
                'name' => '1.1 Thống kê trẻ từ 0-5 tuổi',
                'description' => 'Thống kê trẻ từ 0-5 tuổi',
                'category' => 'mam_non'
            ],
            [
                'code' => 'thong_ke_tre_0_5_tuoi_cu',
                'name' => '1.2 Thống kê trẻ từ 0-5 tuổi cũ',
                'description' => 'Thống kê trẻ từ 0-5 tuổi cũ',
                'category' => 'mam_non'
            ],
            [
                'code' => 'thong_ke_tieu_chuan_phong_hoc',
                'name' => '1.3 Thống kê tiêu chuẩn phòng học, cơ sở vật chất',
                'description' => 'Thống kê tiêu chuẩn phòng học, cơ sở vật chất',
                'category' => 'mam_non'
            ],
            [
                'code' => 'thong_ke_doi_ngu_can_bo_giao_vien',
                'name' => '1.4 Thống kê đội ngũ cán bộ quản lý, giáo viên và nhân viên',
                'description' => 'Thống kê đội ngũ cán bộ quản lý, giáo viên và nhân viên',
                'category' => 'mam_non'
            ],
            [
                'code' => 'bao_cao_tai_chinh',
                'name' => '1.5 Báo cáo tài chính',
                'description' => 'Báo cáo tài chính',
                'category' => 'mam_non'
            ],
            [
                'code' => 'khuyet_tat_mam_non',
                'name' => '1.6 Khuyết tật mầm non',
                'description' => 'Khuyết tật mầm non',
                'category' => 'mam_non'
            ],
            [
                'code' => 'danh_sach_hoc_sinh_phuong_xa_truong',
                'name' => '1.7 Danh sách học sinh các phường xã đang học tại trường',
                'description' => 'Danh sách học sinh các phường xã đang học tại trường',
                'category' => 'mam_non'
            ],
            [
                'code' => 'so_theo_doi_pho_cap_mam_non',
                'name' => '1.8 Sổ theo dõi phổ cập giáo dục mầm non cho trẻ em 5 tuổi',
                'description' => 'Sổ theo dõi phổ cập giáo dục mầm non cho trẻ em 5 tuổi',
                'category' => 'mam_non'
            ],
            [
                'code' => 'thong_ke_tre_cong_lap_ngoai_cong_lap',
                'name' => '1.9 Thống kê trẻ học trường công lập và ngoài công lập',
                'description' => 'Thống kê trẻ học trường công lập và ngoài công lập',
                'category' => 'mam_non'
            ],
            [
                'code' => 'thong_ke_tre_4_tuoi',
                'name' => '1.10 Thống kê trẻ 4 tuổi',
                'description' => 'Thống kê trẻ 4 tuổi',
                'category' => 'mam_non'
            ],

            // 2. Tiểu học
            [
                'code' => 'thong_ke_tre_em_6_14_tuoi',
                'name' => '2.1 Thống kê trẻ em 6-14 tuổi',
                'description' => 'Thống kê trẻ em 6-14 tuổi',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'thong_ke_co_so_vat_chat_tieu_hoc',
                'name' => '2.2 Thống kê cơ sở vật chất giáo dục tiểu học',
                'description' => 'Thống kê cơ sở vật chất giáo dục tiểu học',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'thong_ke_doi_ngu_giao_vien_tieu_hoc',
                'name' => '2.3 Thống kê đội ngũ giáo viên tiểu học',
                'description' => 'Thống kê đội ngũ giáo viên tiểu học',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'thong_ke_hoc_sinh_hoc_ngoai_phuong_xa',
                'name' => '2.4 Thống kê học sinh đang học trường ngoài phường xã',
                'description' => 'Thống kê học sinh đang học trường ngoài phường xã',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'phan_tich_hoc_sinh_trai_tuyen',
                'name' => '2.5 Phân tích số liệu học sinh trái tuyến đang học tại trường',
                'description' => 'Phân tích số liệu học sinh trái tuyến đang học tại trường',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'danh_sach_hoc_sinh_phuong_xa_truong_th',
                'name' => '2.6 Danh sách học sinh các phường xã đang học tại trường',
                'description' => 'Danh sách học sinh các phường xã đang học tại trường',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'thong_ke_khuyet_tat_tieu_hoc',
                'name' => '2.7 Thống kê khuyết tật tiểu học',
                'description' => 'Thống kê khuyết tật tiểu học',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'danh_sach_hoc_sinh_khuyet_tat_tieu_hoc',
                'name' => '2.8 Danh sách học sinh khuyết tật tiểu học',
                'description' => 'Danh sách học sinh khuyết tật tiểu học',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'danh_sach_tre_6_14_chua_di_hoc',
                'name' => '2.9 Danh sách trẻ trong độ tuổi 6-14 chưa đi học và bỏ học',
                'description' => 'Danh sách trẻ trong độ tuổi 6-14 chưa đi học và bỏ học',
                'category' => 'tieu_hoc'
            ],
            [
                'code' => 'so_pho_cap_giao_duc_tieu_hoc',
                'name' => '2.10 Sổ phổ cập giáo dục tiểu học',
                'description' => 'Sổ phổ cập giáo dục tiểu học',
                'category' => 'tieu_hoc'
            ],

            // 3. Trung học cơ sở
            [
                'code' => 'thong_ke_thanh_thieu_nien_pho_cap_thcs',
                'name' => '3.1 Thống kê thanh thiếu niên phổ cập giáo dục THCS',
                'description' => 'Thống kê thanh thiếu niên phổ cập giáo dục THCS',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'ket_qua_huy_dong_pcgd',
                'name' => '3.2 Kết quả huy động PCGD',
                'description' => 'Kết quả huy động PCGD',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'thong_ke_tinh_hinh_co_so_vat_chat_thcs',
                'name' => '3.3 Thống kê tình hình cơ sở vật chất cho dạy và học',
                'description' => 'Thống kê tình hình cơ sở vật chất cho dạy và học',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'thong_ke_tinh_hinh_doi_ngu_giao_vien_thcs',
                'name' => '3.4 Thống kê tình hình đội ngũ giáo viên THCS',
                'description' => 'Thống kê tình hình đội ngũ giáo viên THCS',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'thong_ke_doi_ngu_quan_ly_giao_vien_thcs',
                'name' => '3.5 Thống kê đội ngũ quản lý, giáo viên THCS',
                'description' => 'Thống kê đội ngũ quản lý, giáo viên THCS',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'phan_tich_hoc_sinh_hoc_ngoai_phuong_xa_thcs',
                'name' => '3.6 Phân tích số liệu học sinh đang học ngoài phường xã',
                'description' => 'Phân tích số liệu học sinh đang học ngoài phường xã',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'phan_tich_hoc_sinh_ngoai_phuong_xa_truong_thcs',
                'name' => '3.7 Phân tích số liệu học sinh ngoài phường xã đang học tại trường',
                'description' => 'Phân tích số liệu học sinh ngoài phường xã đang học tại trường',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'danh_sach_hoc_sinh_phuong_xa_truong_thcs',
                'name' => '3.8 Danh sách học sinh các phường xã đang học tại trường',
                'description' => 'Danh sách học sinh các phường xã đang học tại trường',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'thong_ke_khuyet_tat_thcs',
                'name' => '3.9 Thống kê khuyết tật THCS',
                'description' => 'Thống kê khuyết tật THCS',
                'category' => 'trung_hoc_co_so'
            ],
            [
                'code' => 'so_pho_cap_thcs',
                'name' => '3.10 Số phổ cập THCS',
                'description' => 'Số phổ cập THCS',
                'category' => 'trung_hoc_co_so'
            ],

            // 4. Trung học phổ thông
            [
                'code' => 'tong_hop_ket_qua_dieu_tra_pho_cap_thpt',
                'name' => '4.1 Tổng hợp kết quả điều tra phổ cập bậc trung học',
                'description' => 'Tổng hợp kết quả điều tra phổ cập bậc trung học',
                'category' => 'trung_hoc_pho_thong'
            ],
            [
                'code' => 'tong_hop_ket_qua_huy_dong_pho_cap_thpt',
                'name' => '4.2 Tổng hợp kết quả huy động phổ cập bậc trung học',
                'description' => 'Tổng hợp kết quả huy động phổ cập bậc trung học',
                'category' => 'trung_hoc_pho_thong'
            ],
            [
                'code' => 'tong_hop_hieu_qua_pho_cap_thpt',
                'name' => '4.3 Tổng hợp hiệu quả phổ cập bậc trung học',
                'description' => 'Tổng hợp hiệu quả phổ cập bậc trung học',
                'category' => 'trung_hoc_pho_thong'
            ],
            [
                'code' => 'tong_hop_truong_pho_thong_trung_tam',
                'name' => '4.4 Tổng hợp trường phổ thông, trung tâm, cơ sở dạy nghề',
                'description' => 'Tổng hợp trường phổ thông, trung tâm, cơ sở dạy nghề',
                'category' => 'trung_hoc_pho_thong'
            ],
            [
                'code' => 'thong_ke_khuyet_tat_thpt',
                'name' => '4.5 Thống kê khuyết tật THPT',
                'description' => 'Thống kê khuyết tật THPT',
                'category' => 'trung_hoc_pho_thong'
            ],
            [
                'code' => 'so_pho_cap_thpt',
                'name' => '4.6 Sổ phổ cập THPT',
                'description' => 'Sổ phổ cập THPT',
                'category' => 'trung_hoc_pho_thong'
            ],

            // 5. Xóa mù chữ
            [
                'code' => 'tong_hop_tinh_hinh_xoa_mu_chu',
                'name' => '5.1 Tổng hợp tình hình số liệu công tác xóa mù chữ',
                'description' => 'Tổng hợp tình hình số liệu công tác xóa mù chữ',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'tong_hop_ket_qua_xoa_mu_chu',
                'name' => '5.2 Tổng hợp kết quả xóa mù chữ',
                'description' => 'Tổng hợp kết quả xóa mù chữ',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'thong_ke_so_nguoi_mu_chu_theo_do_tuoi',
                'name' => '5.3 Thống kê số người mù chữ theo các độ tuổi',
                'description' => 'Thống kê số người mù chữ theo các độ tuổi',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'thong_ke_danh_sach_nguoi_mu_chu',
                'name' => '5.4 Thống kê danh sách người mù chữ',
                'description' => 'Thống kê danh sách người mù chữ',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'thong_ke_hien_trang_mu_chu_muc_1',
                'name' => '5.5 Thống kê hiện trạng mù chữ mức 1',
                'description' => 'Thống kê hiện trạng mù chữ mức 1',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'thong_ke_hien_trang_mu_chu_muc_2',
                'name' => '5.6 Thống kê hiện trạng mù chữ mức 2',
                'description' => 'Thống kê hiện trạng mù chữ mức 2',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'thong_ke_ket_qua_hoc_xoa_mu_chu_muc_1',
                'name' => '5.7 Thống kê kết quả học xóa mù chữ mức 1',
                'description' => 'Thống kê kết quả học xóa mù chữ mức 1',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'thong_ke_ket_qua_hoc_xoa_mu_chu_muc_2',
                'name' => '5.8 Thống kê kết quả học xóa mù chữ mức 2',
                'description' => 'Thống kê kết quả học xóa mù chữ mức 2',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'thong_ke_ket_qua_xoa_mu_chu',
                'name' => '5.9 Thống kê kết quả xóa mù chữ',
                'description' => 'Thống kê kết quả xóa mù chữ',
                'category' => 'xoa_mu_chu'
            ],
            [
                'code' => 'thong_ke_trinh_do_van_hoa',
                'name' => '5.10 Thống kê trình độ văn hóa',
                'description' => 'Thống kê trình độ văn hóa',
                'category' => 'xoa_mu_chu'
            ],

            // 6. Thống kê khác
            [
                'code' => 'danh_sach_hoc_sinh_tot_nghiep_nam_qua',
                'name' => '6.1 Danh sách học sinh tốt nghiệp năm qua',
                'description' => 'Danh sách học sinh tốt nghiệp năm qua',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_doi_tuong_15_18_tot_nghiep_thcs',
                'name' => '6.2 Danh sách đối tượng 15-18 tuổi tốt nghiệp THCS',
                'description' => 'Danh sách đối tượng 15-18 tuổi tốt nghiệp THCS',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_doi_tuong_khuyet_tat',
                'name' => '6.3 Danh sách đối tượng khuyết tật',
                'description' => 'Danh sách đối tượng khuyết tật',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_doi_tuong_bo_hoc',
                'name' => '6.4 Danh sách đối tượng bỏ học',
                'description' => 'Danh sách đối tượng bỏ học',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_doi_tuong_chuyen_di',
                'name' => '6.5 Danh sách đối tượng chuyển đi',
                'description' => 'Danh sách đối tượng chuyển đi',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_doi_tuong_da_chet',
                'name' => '6.6 Danh sách đối tượng đã chết',
                'description' => 'Danh sách đối tượng đã chết',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'thong_ke_dan_toc_thieu_so',
                'name' => '6.7 Thống kê dân tộc thiểu số',
                'description' => 'Thống kê dân tộc thiểu số',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_hoc_sinh_dia_phuong_di_hoc_noi_khac',
                'name' => '6.8 Danh sách học sinh ở địa phương đi học nơi khác',
                'description' => 'Danh sách học sinh ở địa phương đi học nơi khác',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_hoc_sinh_tot_nghiep_tieu_hoc_11_14',
                'name' => '6.9 Danh sách học sinh tốt nghiệp tiểu học trong độ tuổi PCGD 11-14',
                'description' => 'Danh sách học sinh tốt nghiệp tiểu học trong độ tuổi PCGD 11-14',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_doi_tuong_chuyen_den',
                'name' => '6.10 Danh sách đối tượng chuyển đến',
                'description' => 'Danh sách đối tượng chuyển đến',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'thong_ke_trinh_do_van_hoa_dan_toc_thieu_so',
                'name' => '6.11 Thống kê trình độ văn hóa dân tộc thiểu số',
                'description' => 'Thống kê trình độ văn hóa dân tộc thiểu số',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'thong_ke_trinh_do_van_hoa_doi_tuong_nu',
                'name' => '6.12 Thống kê trình độ văn hóa đối tượng nữ',
                'description' => 'Thống kê trình độ văn hóa đối tượng nữ',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_hoc_sinh_tot_nghiep_5_nam_qua',
                'name' => '6.13 Danh sách học sinh tốt nghiệp 5 năm qua',
                'description' => 'Danh sách học sinh tốt nghiệp 5 năm qua',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'thong_ke_xmc_gd_tiep_tuc_sau_biet_chu',
                'name' => '6.14 Thống kê XMC và GD tiếu tục sau biết chữ',
                'description' => 'Thống kê XMC và GD tiếu tục sau biết chữ',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'tong_hop_so_lieu_cong_tac_xoa_mu_chu',
                'name' => '6.15 Tổng hợp số liệu công tác xóa mù chữ',
                'description' => 'Tổng hợp số liệu công tác xóa mù chữ',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'thong_ke_so_nguoi_mu_chu_theo_dan_toc',
                'name' => '6.16 Thống kê số người mù chữ theo dân tộc',
                'description' => 'Thống kê số người mù chữ theo dân tộc',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_15_18_tn_thcs_tiep_tuc_hoc_thpt',
                'name' => '6.17 Danh sách đối tượng 15-18 tuổi TN THCS tiếp tục học THPT',
                'description' => 'Danh sách đối tượng 15-18 tuổi TN THCS tiếp tục học THPT',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_tn_thcs_nam_qua_tiep_tuc_hoc_thpt',
                'name' => '6.18 Danh sách TN THCS năm qua tiếp tục học THPT',
                'description' => 'Danh sách TN THCS năm qua tiếp tục học THPT',
                'category' => 'thong_ke_khac'
            ],
            [
                'code' => 'danh_sach_luu_ban',
                'name' => '6.19 Danh sách lưu ban',
                'description' => 'Danh sách lưu ban',
                'category' => 'thong_ke_khac'
            ]
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($reportFunctions as $function) {
            // Kiểm tra xem chức năng đã tồn tại chưa
            $existingFunction = SystemFunction::where('code', $function['code'])->first();
            
            if ($existingFunction) {
                $this->command->warn("Chức năng '{$function['name']}' đã tồn tại.");
                $skippedCount++;
                continue;
            }

            // Tạo chức năng mới
            SystemFunction::create([
                'code' => $function['code'],
                'name' => $function['name'],
                'description' => $function['description'],
                'category' => $function['category'],
                'is_active' => true
            ]);

            $createdCount++;
            $this->command->info("Đã tạo chức năng: {$function['name']} ({$function['code']})");
        }

        // Phân quyền cho các role
        $this->assignPermissionsToRoles();

        $this->command->info("Hoàn thành tạo chức năng báo cáo:");
        $this->command->info("- Đã tạo: {$createdCount} chức năng");
        $this->command->info("- Đã bỏ qua: {$skippedCount} chức năng (đã tồn tại)");
    }

    /**
     * Phân quyền cho các role
     */
    private function assignPermissionsToRoles(): void
    {
        $roles = UserRole::all();
        $reportFunctions = SystemFunction::whereIn('category', [
            'mam_non', 'tieu_hoc', 'trung_hoc_co_so', 
            'trung_hoc_pho_thong', 'xoa_mu_chu', 'thong_ke_khac'
        ])->get();

        foreach ($roles as $role) {
            foreach ($reportFunctions as $function) {
                // Kiểm tra xem đã có permission chưa
                $existingPermission = RoleFunctionPermission::where('role_id', $role->id)
                    ->where('function_id', $function->id)
                    ->first();

                if ($existingPermission) {
                    continue;
                }

                // Phân quyền theo role
                $canView = true; // Tất cả role đều có thể xem báo cáo
                $canCreate = false;
                $canEdit = false;
                $canDelete = false;

                switch ($role->name) {
                    case 'admin':
                    case 'superadmin':
                        $canCreate = true;
                        $canEdit = true;
                        $canDelete = true;
                        break;
                    case 'so': // Sở giáo dục
                        $canCreate = true;
                        $canEdit = true;
                        break;
                    case 'xa': // Xã/phường
                        $canCreate = true;
                        break;
                    case 'truong': // Trường học
                        $canCreate = true;
                        break;
                }

                RoleFunctionPermission::create([
                    'role_id' => $role->id,
                    'function_id' => $function->id,
                    'can_view' => $canView,
                    'can_create' => $canCreate,
                    'can_edit' => $canEdit,
                    'can_delete' => $canDelete
                ]);
            }
        }

        $this->command->info("Đã phân quyền báo cáo cho " . $roles->count() . " role");
    }
}
