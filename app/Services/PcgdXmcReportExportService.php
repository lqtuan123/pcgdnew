<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PcgdXmcReportExportService
{
    private $templatePath;
    private $outputPath;

    public function __construct()
    {
        $this->outputPath = storage_path('app/exports');
        
        // Tạo thư mục output nếu chưa có
        if (!file_exists($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
    }

    /**
     * Xuất biên bản PCGD-XMC với dữ liệu từ CKEditor
     */
    public function export(array $data, array $geoInfo = [])
    {
        try {
            // Chọn template dựa trên cấp địa phương
            $isWardLevel = !empty($geoInfo['ward_id']) && $geoInfo['ward_id'] !== '0' && $geoInfo['ward_id'] !== 0;
            $templateFileName = $isWardLevel ? 'bien_ban_pcgd_xmc_template.docx' : 'bien_ban_pcgd_xmc_template_tinh.docx';
            $this->templatePath = resource_path('templates/' . $templateFileName);
            

            
            // Validate geo data
            if (empty($geoInfo['province_id'])) {
                throw new \Exception('Thiếu thông tin tỉnh/thành phố');
            }
            
            // Kiểm tra file template có tồn tại không
            if (!file_exists($this->templatePath)) {
                throw new \Exception('File template không tồn tại: ' . $this->templatePath);
            }

            // 1️⃣ Nạp template
            $templateProcessor = new TemplateProcessor($this->templatePath);

            // 2️⃣ Gán dữ liệu địa phương và năm học
            $templateProcessor->setValue('province_name', $geoInfo['province_name'] ?? '');
            $templateProcessor->setValue('ward_name', $geoInfo['ward_name'] ?? '');
            
            // Tên địa phương in hoa
            $templateProcessor->setValue('province_name_up', mb_strtoupper($geoInfo['province_name'] ?? '', 'UTF-8'));
            $templateProcessor->setValue('ward_name_up', mb_strtoupper($geoInfo['ward_name'] ?? '', 'UTF-8'));
            
            // Năm học (lấy năm đầu của năm học, ví dụ: 2024-2025 -> 2024)
            $academicYear = $geoInfo['academic_year'] ?? date('Y');
            $reportYear = explode('-', $academicYear)[0]; // Lấy năm đầu
            $templateProcessor->setValue('report_year', $reportYear);
            
            // Ngày xuất (ngày hiện tại)
            $templateProcessor->setValue('current_year', date('Y'));
            $templateProcessor->setValue('current_day', date('d'));
            $templateProcessor->setValue('current_month', date('m'));

            // 3️⃣ Tính toán các chỉ số thống kê
            $statistics = $this->calculateStatistics($academicYear, $geoInfo);

            // 4️⃣ Gán dữ liệu từ CKEditor
            $templateProcessor->setValue('uu_diem', $this->cleanHtmlContent($data['uu_diem'] ?? ''));
            $templateProcessor->setValue('nhuoc_diem', $this->cleanHtmlContent($data['nhuoc_diem'] ?? ''));
            $templateProcessor->setValue('de_xuat_kien_nghi', $this->cleanHtmlContent($data['de_xuat_kien_nghi'] ?? ''));

            // 4️⃣.1 Gán dữ liệu điều kiện đảm bảo
            $templateProcessor->setValue('so_nguoi_huy_dong_hoc_xoa_mu_chu', $this->cleanTextContent($data['so_nguoi_huy_dong_hoc_xoa_mu_chu'] ?? ''));
            $templateProcessor->setValue('don_vi_khong_dat_chuan_xoa_mu_chu', $this->cleanTextContent($data['don_vi_khong_dat_chuan_xoa_mu_chu'] ?? ''));
            $templateProcessor->setValue('co_so_vat_chat_mam_non', $this->cleanTextContent($data['co_so_vat_chat_mam_non'] ?? ''));
            $templateProcessor->setValue('co_so_vat_chat_tieu_hoc', $this->cleanTextContent($data['co_so_vat_chat_tieu_hoc'] ?? ''));
            $templateProcessor->setValue('co_so_vat_chat_trung_hoc_co_so', $this->cleanTextContent($data['co_so_vat_chat_trung_hoc_co_so'] ?? ''));
            $templateProcessor->setValue('co_so_vat_chat_trung_tam_gdnn_gdtx', $this->cleanTextContent($data['co_so_vat_chat_trung_tam_gdnn_gdtx'] ?? ''));
            $templateProcessor->setValue('co_so_vat_chat_trung_tam_hoc_tap_cong_dong', $this->cleanTextContent($data['co_so_vat_chat_trung_tam_hoc_tap_cong_dong'] ?? ''));
            $templateProcessor->setValue('kinh_phi_pho_cap_giao_duc', $this->cleanTextContent($data['kinh_phi_pho_cap_giao_duc'] ?? ''));
            $templateProcessor->setValue('kinh_phi_xoa_mu_chu', $this->cleanTextContent($data['kinh_phi_xoa_mu_chu'] ?? ''));
            $templateProcessor->setValue('nguoi_tham_gia_mam_non', $this->cleanTextContent($data['nguoi_tham_gia_mam_non'] ?? ''));
            $templateProcessor->setValue('nguoi_tham_gia_tieu_hoc', $this->cleanTextContent($data['nguoi_tham_gia_tieu_hoc'] ?? ''));
            $templateProcessor->setValue('nguoi_tham_gia_trung_hoc_co_so', $this->cleanTextContent($data['nguoi_tham_gia_trung_hoc_co_so'] ?? ''));
            $templateProcessor->setValue('nguoi_tham_gia_trung_tam_gdnn_gdtx', $this->cleanTextContent($data['nguoi_tham_gia_trung_tam_gdnn_gdtx'] ?? ''));
            $templateProcessor->setValue('nguoi_tham_gia_trung_tam_hoc_tap_cong_dong', $this->cleanTextContent($data['nguoi_tham_gia_trung_tam_hoc_tap_cong_dong'] ?? ''));

            // 5️⃣ Gán các chỉ số thống kê thực tế
            foreach ($statistics as $key => $value) {
                $templateProcessor->setValue($key, $value);
            }

            // 6️⃣ Tạo tên file output
            $filename = 'bien_ban_pcgd_xmc_' . date('Y-m-d_H-i-s') . '.docx';
            $outputFile = $this->outputPath . '/' . $filename;

            // 8️⃣ Xuất file Word mới
            $templateProcessor->saveAs($outputFile);

            return [
                'success' => true,
                'file_path' => $outputFile,
                'filename' => $filename,
                'download_url' => route('front.pcgd-xmc-report.download', ['filename' => $filename])
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Làm sạch nội dung HTML từ CKEditor
     */
    private function cleanHtmlContent(string $htmlContent): string
    {
        if (empty($htmlContent)) {
            return '';
        }

        // Loại bỏ các thẻ HTML không cần thiết nhưng giữ lại format cơ bản
        $content = strip_tags($htmlContent, '<p><br><strong><b><em><i><u><ul><ol><li>');
        
        // Chuyển đổi các thẻ HTML thành format Word-friendly
        $content = str_replace(['<p>', '</p>'], ['', "\n"], $content);
        $content = str_replace(['<br>', '<br/>', '<br />'], ["\n"], $content);
        $content = str_replace(['<strong>', '</strong>', '<b>', '</b>'], ['', ''], $content);
        $content = str_replace(['<em>', '</em>', '<i>', '</i>'], ['', ''], $content);
        $content = str_replace(['<u>', '</u>'], ['', ''], $content);
        
        // Xử lý danh sách
        $content = str_replace(['<ul>', '</ul>'], ['', ''], $content);
        $content = str_replace(['<ol>', '</ol>'], ['', ''], $content);
        $content = str_replace(['<li>', '</li>'], ['• ', "\n"], $content);
        
        // Loại bỏ các ký tự đặc biệt và chuẩn hóa
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = trim($content);
        
        return $content;
    }

    /**
     * Làm sạch nội dung text thông thường (không phải HTML)
     */
    private function cleanTextContent(string $textContent): string
    {
        if (empty($textContent)) {
            return '';
        }

        // Loại bỏ HTML nếu có
        $content = strip_tags($textContent);
        
        // Loại bỏ các ký tự đặc biệt và chuẩn hóa
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $content = trim($content);
        
        return $content;
    }

    /**
     * Tính toán các chỉ số thống kê cho biên bản PCGD-XMC
     */
    private function calculateStatistics(string $academicYear, array $geoInfo): array
    {
        [$y1] = array_map('intval', explode('-', $academicYear));
        
        // Khởi tạo mảng kết quả
        $stats = [];

        // === PHỔ CẬP GIÁO DỤC MẦM NON ===
        $stats = array_merge($stats, $this->calculatePreschoolStatistics($y1, $geoInfo));

        // === PHỔ CẬP GIÁO DỤC TIỂU HỌC ===
        $stats = array_merge($stats, $this->calculatePrimaryStatistics($y1, $geoInfo));

        // === PHỔ CẬP GIÁO DỤC TRUNG HỌC ===
        $stats = array_merge($stats, $this->calculateSecondaryStatistics($y1, $geoInfo));

        // === XÓA MÙ CHỮ ===
        $stats = array_merge($stats, $this->calculateLiteracyStatistics($y1, $geoInfo));

        return $stats;
    }

    /**
     * Tính toán chỉ số phổ cập giáo dục mầm non
     */
    private function calculatePreschoolStatistics(int $year, array $geoInfo): array
    {
        $stats = [];

        // Trẻ 5 tuổi huy động đến trường
        $tre5TuoiData = $this->queryPreschool5YearOlds($year, $geoInfo);
        $stats['tre_5_tuoi_huy_dong_den_truong_so_luong'] = $tre5TuoiData['enrolled'];
        $stats['tre_5_tuoi_huy_dong_den_truong_tong_so'] = $tre5TuoiData['total'];
        $stats['tre_5_tuoi_huy_dong_den_truong_ty_le'] = $this->calculatePercentage($tre5TuoiData['enrolled'], $tre5TuoiData['total']);

        // Trẻ học 2 buổi/ngày
        $tre2BuoiData = $this->queryPreschool2SessionStudents($year, $geoInfo);
        $stats['tre_hoc_2_buoi_so_luong'] = $tre2BuoiData['two_session'];
        $stats['tre_hoc_2_buoi_tong_so'] = $tre2BuoiData['total'];
        $stats['tre_hoc_2_buoi_ty_le'] = $this->calculatePercentage($tre2BuoiData['two_session'], $tre2BuoiData['total']);

        // Trẻ 5 tuổi hoàn thành chương trình
        $tre5TuoiHoanThanhData = $this->queryPreschool5YearOldCompletion($year, $geoInfo);
        $stats['tre_5_tuoi_hoan_thanh_ct_so_luong'] = $tre5TuoiHoanThanhData['completed'];
        $stats['tre_5_tuoi_hoan_thanh_ct_tong_so'] = $tre5TuoiHoanThanhData['total'];
        $stats['tre_5_tuoi_hoan_thanh_ct_ty_le'] = $this->calculatePercentage($tre5TuoiHoanThanhData['completed'], $tre5TuoiHoanThanhData['total']);

        // Trẻ khuyết tật
        $treKhuyetTatData = $this->queryPreschoolDisabledStudents($year, $geoInfo);
        $stats['tre_5_tuoi_khuyet_tat_tong_so'] = $treKhuyetTatData['total_disabled'];
        $stats['tre_5_tuoi_khuyet_tat_co_kha_nang_hoc'] = $treKhuyetTatData['can_learn'];
        $stats['tre_5_tuoi_khuyet_tat_tiep_can_gd_so_luong'] = $treKhuyetTatData['access_education'];
        $stats['tre_5_tuoi_khuyet_tat_tiep_can_gd_tong_so'] = $treKhuyetTatData['can_learn']; // Mẫu số là số trẻ có khả năng học tập
        $stats['tre_5_tuoi_khuyet_tat_tiep_can_gd_ty_le'] = $this->calculatePercentage($treKhuyetTatData['access_education'], $treKhuyetTatData['can_learn']);
        $stats['tre_5_tuoi_khuyet_tat_hoc_noi_khac'] = $treKhuyetTatData['access_outside'];
        $stats['tre_5_tuoi_khuyet_tat_noi_khac_den_hoc'] = $treKhuyetTatData['outside_to_local'];

        return $stats;
    }

    /**
     * Tính toán chỉ số phổ cập giáo dục tiểu học
     */
    private function calculatePrimaryStatistics(int $year, array $geoInfo): array
    {
        $stats = [];

        // Trẻ 6 tuổi vào lớp 1
        $tre6TuoiData = $this->queryPrimary6YearOlds($year, $geoInfo);
        $stats['tre_6_tuoi_vao_lop_1_so_luong'] = $tre6TuoiData['enrolled'];
        $stats['tre_6_tuoi_vao_lop_1_tong_so'] = $tre6TuoiData['total'];
        $stats['tre_6_tuoi_vao_lop_1_ty_le'] = $this->calculatePercentage($tre6TuoiData['enrolled'], $tre6TuoiData['total']);

        // Trẻ đến 14 tuổi hoàn thành TH
        $tre14TuoiData = $this->queryPrimary14YearOldCompletion($year, $geoInfo);
        $stats['tre_den_14_tuoi_hoan_thanh_th_so_luong'] = $tre14TuoiData['completed'];
        $stats['tre_den_14_tuoi_hoan_thanh_th_tong_so'] = $tre14TuoiData['total'];
        $stats['tre_den_14_tuoi_hoan_thanh_th_ty_le'] = $this->calculatePercentage($tre14TuoiData['completed'], $tre14TuoiData['total']);

        // Trẻ 11 tuổi hoàn thành TH
        $tre11TuoiData = $this->queryPrimary11YearOldCompletion($year, $geoInfo);
        $stats['tre_11_tuoi_hoan_thanh_th_so_luong'] = $tre11TuoiData['completed'];
        $stats['tre_11_tuoi_hoan_thanh_th_tong_so'] = $tre11TuoiData['total'];
        $stats['tre_11_tuoi_hoan_thanh_th_ty_le'] = $this->calculatePercentage($tre11TuoiData['completed'], $tre11TuoiData['total']);
        $stats['tre_11_tuoi_con_lai_hoc_th_so_luong'] = $tre11TuoiData['still_studying'];
        $stats['tre_11_tuoi_con_lai_hoc_th_ty_le'] = $this->calculatePercentage($tre11TuoiData['still_studying'], $tre11TuoiData['total']);

        // Trẻ khuyết tật tiếp cận GD TH
        $treKTData = $this->queryPrimaryDisabledStudents($year, $geoInfo);
        $stats['tre_khuyet_tat_tiep_can_gd_th_so_luong'] = $treKTData['access_education'];
        $stats['tre_khuyet_tat_tiep_can_gd_th_tong_so'] = $treKTData['can_learn'];
        $stats['tre_khuyet_tat_tiep_can_gd_th_ty_le'] = $this->calculatePercentage($treKTData['access_education'], $treKTData['can_learn']);

        return $stats;
    }

    /**
     * Tính toán chỉ số phổ cập giáo dục trung học
     */
    private function calculateSecondaryStatistics(int $year, array $geoInfo): array
    {
        $stats = [];

        // Thanh thiếu niên 15-18 tốt nghiệp THCS
        $tn15_18Data = $this->querySecondary15_18Graduation($year, $geoInfo);
        $stats['thanh_thieu_nien_15_18_tot_nghiep_thcs_so_luong'] = $tn15_18Data['graduated'];
        $stats['thanh_thieu_nien_15_18_tot_nghiep_thcs_tong_so'] = $tn15_18Data['total'];
        $stats['thanh_thieu_nien_15_18_tot_nghiep_thcs_ty_le'] = $this->calculatePercentage($tn15_18Data['graduated'], $tn15_18Data['total']);

        // Thanh thiếu niên 15-18 đang học THPT
        $tn15_18HocData = $this->querySecondary15_18Studying($year, $geoInfo);
        $stats['thanh_thieu_nien_15_18_dang_hoc_thpt_so_luong'] = $tn15_18HocData['studying'];
        $stats['thanh_thieu_nien_15_18_dang_hoc_thpt_tong_so'] = $tn15_18HocData['total'];
        $stats['thanh_thieu_nien_15_18_dang_hoc_thpt_ty_le'] = $this->calculatePercentage($tn15_18HocData['studying'], $tn15_18HocData['total']);

        // Xã đạt chuẩn PCGD THCS
        $xaDatChuanData = $this->queryWardsMeetingSecondaryStandards($year, $geoInfo);
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_1_so_luong'] = $xaDatChuanData['level_1'];
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_1_tong_so'] = $xaDatChuanData['total_wards'];
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_1_ty_le'] = $this->calculatePercentage($xaDatChuanData['level_1'], $xaDatChuanData['total_wards']);
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_2_so_luong'] = $xaDatChuanData['level_2'];
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_2_tong_so'] = $xaDatChuanData['total_wards'];
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_2_ty_le'] = $this->calculatePercentage($xaDatChuanData['level_2'], $xaDatChuanData['total_wards']);
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_3_so_luong'] = $xaDatChuanData['level_3'];
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_3_tong_so'] = $xaDatChuanData['total_wards'];
        $stats['xa_dat_chuan_pcgd_thcs_muc_do_3_ty_le'] = $this->calculatePercentage($xaDatChuanData['level_3'], $xaDatChuanData['total_wards']);

        return $stats;
    }

    /**
     * Tính toán chỉ số xóa mù chữ
     */
    private function calculateLiteracyStatistics(int $year, array $geoInfo): array
    {
        $stats = [];

        // Dân số 15-25
        $danSo15_25Data = $this->queryLiteracy15_25($year, $geoInfo);
        $stats['dan_so_15_25_tong_so'] = $danSo15_25Data['total'];
        $stats['dan_so_15_25_dat_chuan_biet_chu_muc_do_1_so_luong'] = $danSo15_25Data['literate_level1'];
        $stats['dan_so_15_25_dat_chuan_biet_chu_muc_do_1_ty_le'] = $this->calculatePercentage($danSo15_25Data['literate_level1'], $danSo15_25Data['total']);
        $stats['dan_so_15_25_dat_chuan_biet_chu_muc_do_2_so_luong'] = $danSo15_25Data['literate_level2'];
        $stats['dan_so_15_25_dat_chuan_biet_chu_muc_do_2_ty_le'] = $this->calculatePercentage($danSo15_25Data['literate_level2'], $danSo15_25Data['total']);
        $stats['dan_so_15_25_chua_dat_chuan_muc_do_1_so_luong'] = $danSo15_25Data['illiterate_level1'];
        $stats['dan_so_15_25_chua_dat_chuan_muc_do_1_ty_le'] = $this->calculatePercentage($danSo15_25Data['illiterate_level1'], $danSo15_25Data['total']);
        $stats['dan_so_15_25_mu_chu_so_luong'] = $danSo15_25Data['completely_illiterate'];
        $stats['dan_so_15_25_mu_chu_ty_le'] = $this->calculatePercentage($danSo15_25Data['completely_illiterate'], $danSo15_25Data['total']);
        $stats['dan_so_15_25_chua_dat_chuan_muc_do_2_so_luong'] = $danSo15_25Data['illiterate_level2'];
        $stats['dan_so_15_25_chua_dat_chuan_muc_do_2_ty_le'] = $this->calculatePercentage($danSo15_25Data['illiterate_level2'], $danSo15_25Data['total']);

        // Dân số 15-35
        $danSo15_35Data = $this->queryLiteracy15_35($year, $geoInfo);
        $stats['dan_so_15_35_tong_so'] = $danSo15_35Data['total'];
        $stats['dan_so_15_35_dat_chuan_biet_chu_muc_do_1_so_luong'] = $danSo15_35Data['literate_level1'];
        $stats['dan_so_15_35_dat_chuan_biet_chu_muc_do_1_ty_le'] = $this->calculatePercentage($danSo15_35Data['literate_level1'], $danSo15_35Data['total']);
        $stats['dan_so_15_35_dat_chuan_biet_chu_muc_do_2_so_luong'] = $danSo15_35Data['literate_level2'];
        $stats['dan_so_15_35_dat_chuan_biet_chu_muc_do_2_ty_le'] = $this->calculatePercentage($danSo15_35Data['literate_level2'], $danSo15_35Data['total']);
        $stats['dan_so_15_35_chua_dat_chuan_muc_do_1_so_luong'] = $danSo15_35Data['illiterate_level1'];
        $stats['dan_so_15_35_chua_dat_chuan_muc_do_1_ty_le'] = $this->calculatePercentage($danSo15_35Data['illiterate_level1'], $danSo15_35Data['total']);
        $stats['dan_so_15_35_mu_chu_so_luong'] = $danSo15_35Data['completely_illiterate'];
        $stats['dan_so_15_35_mu_chu_ty_le'] = $this->calculatePercentage($danSo15_35Data['completely_illiterate'], $danSo15_35Data['total']);
        $stats['dan_so_15_35_chua_dat_chuan_muc_do_2_so_luong'] = $danSo15_35Data['illiterate_level2'];
        $stats['dan_so_15_35_chua_dat_chuan_muc_do_2_ty_le'] = $this->calculatePercentage($danSo15_35Data['illiterate_level2'], $danSo15_35Data['total']);

        // Dân số 15-60
        $danSo15_60Data = $this->queryLiteracy15_60($year, $geoInfo);
        $stats['dan_so_15_60_tong_so'] = $danSo15_60Data['total'];
        $stats['dan_so_15_60_dat_chuan_biet_chu_muc_do_1_so_luong'] = $danSo15_60Data['literate_level1'];
        $stats['dan_so_15_60_dat_chuan_biet_chu_muc_do_1_ty_le'] = $this->calculatePercentage($danSo15_60Data['literate_level1'], $danSo15_60Data['total']);
        $stats['dan_so_15_60_dat_chuan_biet_chu_muc_do_2_so_luong'] = $danSo15_60Data['literate_level2'];
        $stats['dan_so_15_60_dat_chuan_biet_chu_muc_do_2_ty_le'] = $this->calculatePercentage($danSo15_60Data['literate_level2'], $danSo15_60Data['total']);
        $stats['dan_so_15_60_chua_dat_chuan_muc_do_1_so_luong'] = $danSo15_60Data['illiterate_level1'];
        $stats['dan_so_15_60_chua_dat_chuan_muc_do_1_ty_le'] = $this->calculatePercentage($danSo15_60Data['illiterate_level1'], $danSo15_60Data['total']);
        $stats['dan_so_15_60_mu_chu_so_luong'] = $danSo15_60Data['completely_illiterate'];
        $stats['dan_so_15_60_mu_chu_ty_le'] = $this->calculatePercentage($danSo15_60Data['completely_illiterate'], $danSo15_60Data['total']);
        $stats['dan_so_15_60_chua_dat_chuan_muc_do_2_so_luong'] = $danSo15_60Data['illiterate_level2'];
        $stats['dan_so_15_60_chua_dat_chuan_muc_do_2_ty_le'] = $this->calculatePercentage($danSo15_60Data['illiterate_level2'], $danSo15_60Data['total']);

        // Số người huy động học XMC
        $stats['so_nguoi_huy_dong_hoc_xoa_mu_chu'] = $this->queryLiteracyEnrollment($year, $geoInfo);

        return $stats;
    }

    /**
     * Tính phần trăm
     */
    private function calculatePercentage(int $numerator, int $denominator): string
    {
        if ($denominator == 0) {
            return '0.00';
        }
        return number_format(($numerator / $denominator) * 100, 2);
    }

    // === CÁC PHƯƠNG THỨC QUERY DỮ LIỆU MẦM NON ===

    private function queryPreschool5YearOlds(int $year, array $geoInfo): array
    {
        // Logic từ R11Report - tính trẻ 5 tuổi huy động đến trường
        $academicYear = $year . '-' . ($year + 1);
        $dobYear = $year - 5; // Trẻ 5 tuổi có năm sinh = năm báo cáo - 5
        
        // Query từ v_survey_detail_flat
        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academicYear)
            ->where('v.dob_year', $dobYear);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('v.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('v.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện
        $isEnrolled = "(v.education_block_code IS NOT NULL)";
        $disAny = "(
            COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
            COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
            COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
        ) > 0 OR v.dis_has_certificate=1";
        $movedOutOrDead = "v.sc_name IN ('Chuyển đi','MOVED_OUT','Chết','DECEASED')";

        $result = $qb->selectRaw("
            COUNT(DISTINCT v.person_id) AS total,
            (
                COUNT(DISTINCT v.person_id)
                - COUNT(DISTINCT CASE WHEN $disAny THEN v.person_id END)
                - COUNT(DISTINCT CASE WHEN $movedOutOrDead THEN v.person_id END)
            ) AS must_mobilize,
            COUNT(DISTINCT CASE WHEN $isEnrolled THEN v.person_id END) AS enrolled
        ")->first();

        return [
            'enrolled' => (int)($result->enrolled ?? 0),
            'total' => max(0, (int)($result->must_mobilize ?? 0))
        ];
    }

    private function queryPreschool2SessionStudents(int $year, array $geoInfo): array
    {
        // Logic từ R11Report - tính trẻ học 2 buổi/ngày
        $academicYear = $year . '-' . ($year + 1);
        $dobYear = $year - 5; // Trẻ 5 tuổi có năm sinh = năm báo cáo - 5
        
        // Query từ v_survey_detail_flat với person_mamnon và person_educations
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.person_id', 'pe1.hoc_hai_buoi as hoc_2_buoi')
            ->where('pe1.academic_year', $academicYear)
            ->whereRaw(
                'pe1.id = (
                    SELECT MAX(pe2.id)
                    FROM person_educations pe2
                    WHERE pe2.person_id = pe1.person_id
                    AND pe2.academic_year = ?
                )',
                [$academicYear]
            );

        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('person_mamnon as pm', 'pm.person_id', '=', 'v.person_id')
            ->leftJoinSub($peLatest, 'pe', function ($j) {
                $j->on('pe.person_id', '=', 'v.person_id');
            })
            ->where('v.academic_year', $academicYear)
            ->where('v.dob_year', $dobYear);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('v.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('v.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện
        $isEnrolled = "(v.education_block_code IS NOT NULL)";
        $twoSessions = "(COALESCE(pm.hoc_2_buoi,0)=1 OR COALESCE(pe.hoc_2_buoi,0)=1)";

        $result = $qb->selectRaw("
            COUNT(DISTINCT CASE WHEN $isEnrolled THEN v.person_id END) AS total_enrolled,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND $twoSessions THEN v.person_id END) AS two_sessions
        ")->first();

        return [
            'two_session' => (int)($result->two_sessions ?? 0),
            'total' => (int)($result->total_enrolled ?? 0)
        ];
    }

    private function queryPreschool5YearOldCompletion(int $year, array $geoInfo): array
    {
        // Logic từ R11Report - tính trẻ 5 tuổi hoàn thành chương trình GDMN
        // Theo báo cáo 1.1: Lấy trẻ 6 tuổi (đã hoàn thành GDMN năm trước) thay vì trẻ 5 tuổi
        $academicYear = $year . '-' . ($year + 1);
        $dobYear = $year - 6; // Trẻ 6 tuổi có năm sinh = năm báo cáo - 6 (đã hoàn thành GDMN năm trước)
        
        // Query từ v_survey_detail_flat với person_mamnon
        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('person_mamnon as pm', 'pm.person_id', '=', 'v.person_id')
            ->where('v.academic_year', $academicYear)
            ->where('v.dob_year', $dobYear);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('v.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('v.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện: Trẻ 6 tuổi đã hoàn thành chương trình GDMN (theo báo cáo 1.1)
        // Tử số: Trẻ 6 tuổi không khuyết tật đã hoàn thành GDMN
        // Mẫu số: Trẻ 6 tuổi không khuyết tật phải huy động
        $isEnrolled = "(v.education_block_code IS NOT NULL)";
        $completedGDMN = "(COALESCE(pm.hoan_thanh_chuong_trinh_gdmn,0)=1 OR (v.graduation_level_name='MN'))";
        
        // Điều kiện trẻ khuyết tật (theo logic từ queryPreschoolDisabledStudents)
        $disAny = "(
            COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
            COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
            COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
        ) > 0 OR v.dis_has_certificate=1";
        $notDisabled = "NOT ($disAny)"; // Không phải trẻ khuyết tật

        $result = $qb->selectRaw("
            COUNT(DISTINCT CASE WHEN $isEnrolled AND $notDisabled THEN v.person_id END) AS total_mobilized,
            COUNT(DISTINCT CASE WHEN $isEnrolled AND $completedGDMN AND $notDisabled THEN v.person_id END) AS completed
        ")->first();

        return [
            'completed' => (int)($result->completed ?? 0),
            'total' => (int)($result->total_mobilized ?? 0)
        ];
    }

    private function queryPreschoolDisabledStudents(int $year, array $geoInfo): array
    {
        // Logic từ R11Report - tính các chỉ số khuyết tật trẻ 5 tuổi
        $academicYear = $year . '-' . ($year + 1);
        $dobYear = $year - 5; // Trẻ 5 tuổi có năm sinh = năm báo cáo - 5
        
        // Query từ v_survey_detail_flat
        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academicYear)
            ->where('v.dob_year', $dobYear);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('v.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('v.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện
        $disAny = "(
            COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
            COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
            COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
        ) > 0 OR v.dis_has_certificate=1";
        $isEnrolled = "(v.education_block_code IS NOT NULL)";
        $canLearn = "(v.dis_can_learn=1)";
        
        // Ward ID để phân biệt học tại địa bàn hay nơi khác
        $wardId = $geoInfo['ward_id'] ?? null;
        // Nếu có ward_id thì chỉ tính học tại xã đó, nếu không thì tính tất cả trong tỉnh
        $enrolledLocal = $wardId ? "(s.new_ward_id = $wardId)" : "1=1";
        $enrolledOutside = $wardId ? "(s.new_ward_id != $wardId OR s.new_ward_id IS NULL)" : "1=0";

        $result = $qb->selectRaw("
            COUNT(DISTINCT CASE WHEN $disAny THEN v.person_id END) AS total_disabled,
            COUNT(DISTINCT CASE WHEN v.dis_can_learn=1 THEN v.person_id END) AS can_learn,
            COUNT(DISTINCT CASE WHEN ($disAny) AND $isEnrolled THEN v.person_id END) AS access_education,
            COUNT(DISTINCT CASE WHEN ($disAny) AND $isEnrolled AND $enrolledLocal THEN v.person_id END) AS access_local,
            COUNT(DISTINCT CASE WHEN ($disAny) AND $isEnrolled AND $enrolledOutside THEN v.person_id END) AS access_outside
        ")->first();

        // Query trẻ khuyết tật từ nơi khác đến học tại địa bàn này từ bảng students_off_track
        // Logic: Học sinh trái tuyến 5 tuổi, chưa xóa, bị khuyết tật và được quản lý tại địa bàn này
        $outsideDisabledQuery = DB::table('students_off_track as sot')
            ->leftJoin('schools as s', 's.id', '=', 'sot.school_id')
            ->where('sot.academic_year', $academicYear)
            ->where('sot.dob_year', $dobYear)
            ->where('sot.is_deleted', false);

        // Điều kiện khuyết tật
        $sotDisAny = "(
            COALESCE(sot.disability_van_dong,0)+COALESCE(sot.disability_nghe_noi,0)+COALESCE(sot.disability_nhin,0)+
            COALESCE(sot.disability_than_kinh,0)+COALESCE(sot.disability_tri_tue,0)+COALESCE(sot.disability_hoc_tap,0)+
            COALESCE(sot.disability_tu_ky,0)+COALESCE(sot.disability_khac,0)
        ) > 0 OR sot.disability_has_certificate=1";

        if (!empty($geoInfo['province_id'])) {
            $outsideDisabledQuery->where('sot.selected_province_code_new', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            // Được quản lý tại địa bàn này
            $outsideDisabledQuery->where('sot.selected_ward_code_new', $geoInfo['ward_id']);
        }

        $outsideDisabledResult = $outsideDisabledQuery->whereRaw($sotDisAny)->selectRaw("
            COUNT(DISTINCT sot.id) AS outside_disabled_count
        ")->first();

        return [
            'total_disabled' => (int)($result->total_disabled ?? 0),
            'can_learn' => (int)($result->can_learn ?? 0),
            'access_education' => (int)($result->access_education ?? 0),
            'access_local' => (int)($result->access_local ?? 0),
            'access_outside' => (int)($result->access_outside ?? 0),
            'outside_to_local' => (int)($outsideDisabledResult->outside_disabled_count ?? 0)
        ];
    }

    // === CÁC PHƯƠNG THỨC QUERY DỮ LIỆU TIỂU HỌC ===

    private function queryPrimary6YearOlds(int $year, array $geoInfo): array
    {
        // Logic từ TiH614Report - tính trẻ 6 tuổi vào lớp 1
        $academicYear = $year . '-' . ($year + 1);
        $dobYear = $year - 6; // Trẻ 6 tuổi có năm sinh = năm báo cáo - 6
        
        // Query từ survey_form_details với person_educations
        $peLatestForYear = DB::table('person_educations as pe1')
            ->select(
                'pe1.*',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn')
            )
            ->where('pe1.academic_year', $academicYear);

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoinSub($peLatestForYear, 'pe', function($j){
                $j->on('pe.person_id','=','d.person_id')->where('pe.rn', 1);
            })
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('person_disabilities as pd', 'pd.id', '=', 'd.person_disability_id')
            ->where('d.is_deleted', false)
            ->where('p.dob_year', $dobYear);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('f.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('f.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện
        $notDisabledExpr = "(pd.id IS NULL OR JSON_LENGTH(COALESCE(pd.disability_ids,'[]')) = 0)";
        $notEnrolledByBlockExpr = "(pe.education_block_id IS NULL OR CAST(eb.name AS UNSIGNED) NOT BETWEEN 1 AND 12)";
        $enrolledInGrade1 = "(CAST(eb.name AS UNSIGNED) = 1 AND pe.school_id IS NOT NULL)";

        $result = $qb->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN {$notDisabledExpr} THEN 1 ELSE 0 END) as ppc,
            SUM(CASE WHEN {$notDisabledExpr} AND ({$notEnrolledByBlockExpr}) THEN 1 ELSE 0 END) as non_dis_not_enrolled
        ")->first();

        // Logic từ TiH614Report: enrolled = ppc - non_dis_not_enrolled
        $enrolled = max(0, (int)($result->ppc ?? 0) - (int)($result->non_dis_not_enrolled ?? 0));

        return [
            'enrolled' => $enrolled,
            'total' => (int)($result->total ?? 0)
        ];
    }

    private function queryPrimary14YearOldCompletion(int $year, array $geoInfo): array
    {
        // Logic từ TiH614Report - tính trẻ đến 14 tuổi hoàn thành TH
        $academicYear = $year . '-' . ($year + 1);
        
        // Tính tổng từ năm sinh 11-14 tuổi
        $years = [];
        for ($age = 11; $age <= 14; $age++) {
            $years[] = $year - $age;
        }
        
        // Query từ survey_form_details với person_educations
        $peLatestForYear = DB::table('person_educations as pe1')
            ->select(
                'pe1.*',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn')
            )
            ->where('pe1.academic_year', $academicYear);

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoinSub($peLatestForYear, 'pe', function($j){
                $j->on('pe.person_id','=','d.person_id')->where('pe.rn', 1);
            })
            ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
            ->leftJoin('person_disabilities as pd', 'pd.id', '=', 'd.person_disability_id')
            ->where('d.is_deleted', false)
            ->whereIn('p.dob_year', $years);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('f.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('f.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện
        $notDisabledExpr = "(pd.id IS NULL OR JSON_LENGTH(COALESCE(pd.disability_ids,'[]')) = 0)";
        $completedTH = "(UPPER(TRIM(gl.name)) = 'TH' AND pe.school_id IS NOT NULL)";

        $result = $qb->selectRaw("
            SUM(CASE WHEN {$notDisabledExpr} THEN 1 ELSE 0 END) as ppc,
            SUM(CASE WHEN {$notDisabledExpr} AND {$completedTH} THEN 1 ELSE 0 END) as completed_th
        ")->first();

        return [
            'completed' => (int)($result->completed_th ?? 0),
            'total' => (int)($result->ppc ?? 0)
        ];
    }

    private function queryPrimary11YearOldCompletion(int $year, array $geoInfo): array
    {
        // Logic từ TiH614Report - tính trẻ 11 tuổi hoàn thành TH và còn lại đang học
        $academicYear = $year . '-' . ($year + 1);
        $dobYear = $year - 11; // Trẻ 11 tuổi có năm sinh = năm báo cáo - 11
        
        // Query từ survey_form_details với person_educations
        $peLatestForYear = DB::table('person_educations as pe1')
            ->select(
                'pe1.*',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn')
            )
            ->where('pe1.academic_year', $academicYear);

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoinSub($peLatestForYear, 'pe', function($j){
                $j->on('pe.person_id','=','d.person_id')->where('pe.rn', 1);
            })
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('graduation_levels as gl', 'gl.id', '=', 'pe.graduation_level_id')
            ->leftJoin('person_disabilities as pd', 'pd.id', '=', 'd.person_disability_id')
            ->where('d.is_deleted', false)
            ->where('p.dob_year', $dobYear);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('f.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('f.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện
        $notDisabledExpr = "(pd.id IS NULL OR JSON_LENGTH(COALESCE(pd.disability_ids,'[]')) = 0)";
        $completedTH = "(UPPER(TRIM(gl.name)) = 'TH' AND pe.school_id IS NOT NULL)";
        $stillStudyingTH = "(CAST(eb.name AS UNSIGNED) BETWEEN 1 AND 5 AND pe.school_id IS NOT NULL)";

        $result = $qb->selectRaw("
            SUM(CASE WHEN {$notDisabledExpr} THEN 1 ELSE 0 END) as ppc,
            SUM(CASE WHEN {$notDisabledExpr} AND {$completedTH} THEN 1 ELSE 0 END) as completed_th,
            SUM(CASE WHEN {$notDisabledExpr} AND {$stillStudyingTH} THEN 1 ELSE 0 END) as still_studying_th
        ")->first();

        return [
            'completed' => (int)($result->completed_th ?? 0),
            'total' => (int)($result->ppc ?? 0),
            'still_studying' => (int)($result->still_studying_th ?? 0)
        ];
    }

    private function queryPrimaryDisabledStudents(int $year, array $geoInfo): array
    {
        // Logic từ TiH614Report - tính trẻ khuyết tật có khả năng học tập được tiếp cận giáo dục TH
        $academicYear = $year . '-' . ($year + 1);
        
        // Tính tổng từ năm sinh 6-14 tuổi (độ tuổi tiểu học)
        $years = [];
        for ($age = 6; $age <= 14; $age++) {
            $years[] = $year - $age;
        }
        
        // Query từ survey_form_details với person_educations
        $peLatestForYear = DB::table('person_educations as pe1')
            ->select(
                'pe1.*',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) AS rn')
            )
            ->where('pe1.academic_year', $academicYear);

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id', '=', 'd.person_id')
            ->leftJoinSub($peLatestForYear, 'pe', function($j){
                $j->on('pe.person_id','=','d.person_id')->where('pe.rn', 1);
            })
            ->leftJoin('education_blocks as eb', 'eb.id', '=', 'pe.education_block_id')
            ->leftJoin('person_disabilities as pd', 'pd.id', '=', 'd.person_disability_id')
            ->where('d.is_deleted', false)
            ->whereIn('p.dob_year', $years);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('f.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('f.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện
        $disabledExpr = "JSON_LENGTH(COALESCE(pd.disability_ids,'[]')) > 0";
        $canLearn = "(pd.can_learn = 1)";
        $accessEducation = "(pe.education_block_id IS NOT NULL AND CAST(eb.name AS UNSIGNED) BETWEEN 1 AND 5)";

        $result = $qb->selectRaw("
            SUM(CASE WHEN {$disabledExpr} AND {$canLearn} THEN 1 ELSE 0 END) as can_learn,
            SUM(CASE WHEN {$disabledExpr} AND {$canLearn} AND {$accessEducation} THEN 1 ELSE 0 END) as access_education
        ")->first();

        return [
            'can_learn' => (int)($result->can_learn ?? 0),
            'access_education' => (int)($result->access_education ?? 0)
        ];
    }

    // === CÁC PHƯƠNG THỨC QUERY DỮ LIỆU TRUNG HỌC ===

    private function querySecondary15_18Graduation(int $year, array $geoInfo): array
    {
        // Logic từ R31Report - tính thanh thiếu niên 15-18 tuổi tốt nghiệp THCS
        $academicYear = $year . '-' . ($year + 1);
        
        // Tính tổng từ năm sinh 15-18 tuổi
        $years = [];
        for ($age = 15; $age <= 18; $age++) {
            $years[] = $year - $age;
        }
        
        // Query từ v_survey_detail_flat
        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academicYear)
            ->whereIn('v.dob_year', $years);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('v.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('v.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện từ R31Report
        $graduatedTHCS = "(v.graduation_level_name = 'THCS' OR v.graduation_level_name = 'THPT')";
        $notDisabled = "(
            COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
            COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
            COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
        ) = 0 AND v.dis_has_certificate=0";
        $movedOutOrDead = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','DECEASED','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";

        $result = $qb->selectRaw("
            (
                COUNT(DISTINCT v.person_id)
                - COUNT(DISTINCT CASE WHEN NOT ($notDisabled) THEN v.person_id END)
                - COUNT(DISTINCT CASE WHEN ($movedOutOrDead) THEN v.person_id END)
            ) AS total_must_pc,
            COUNT(DISTINCT CASE WHEN ($notDisabled) AND ($graduatedTHCS) THEN v.person_id END) AS graduated_thcs
        ")->first();

        return [
            'graduated' => (int)($result->graduated_thcs ?? 0),
            'total' => max(0, (int)($result->total_must_pc ?? 0))
        ];
    }

    private function querySecondary15_18Studying(int $year, array $geoInfo): array
    {
        // Logic từ R31Report - tính thanh thiếu niên 15-18 tuổi đang học THPT/GDTX/GDNN
        $academicYear = $year . '-' . ($year + 1);
        
        // Tính tổng từ năm sinh 15-18 tuổi
        $years = [];
        for ($age = 15; $age <= 18; $age++) {
            $years[] = $year - $age;
        }
        
        // Query từ v_survey_detail_flat
        $qb = DB::table('v_survey_detail_flat as v')
            ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
            ->where('v.academic_year', $academicYear)
            ->whereIn('v.dob_year', $years);

        // Lọc địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('v.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('v.new_ward_id', $geoInfo['ward_id']);
        }

        // Điều kiện từ R31Report
        $notDisabled = "(
            COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
            COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
            COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
        ) = 0 AND v.dis_has_certificate=0";
        $movedOutOrDead = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','DECEASED','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";
        
        // Đang học THPT/GDTX/GDNN - Logic từ R31Report
        $pt_gdnn = "((v.education_block_code IS NOT NULL AND v.graduation_level_name = 'THCS'))";
        $tnpt = "(v.graduation_level_name = 'THPT')";
        $isPT = "((s.school_type_id IS NULL OR s.school_type_id NOT IN (3, 11, 1)))";
        $isTX = "(v.is_bo_tuc = 1 OR s.school_type_id IN (3, 11))";
        $isVOC = "(s.school_type_id = 1 OR v.vocational_level_name = 'Trung cấp')";
        
        // Logic local từ R31Report: học tại trường thuộc địa bàn (loại trừ trường ngoài tỉnh)
        $local = $wardId ? "(s.code NOT LIKE 'NGOAI_TINH%' AND s.new_ward_id = $wardId)" : "(s.code NOT LIKE 'NGOAI_TINH%')";
        
        // Logic từ R31Report: chỉ tính học tại địa bàn (local)
        $studyingAfterTHCS = "($tnpt OR ($pt_gdnn AND $local AND $isPT) OR ($pt_gdnn AND $isTX AND $local) OR ($isVOC AND $local))";

        $result = $qb->selectRaw("
            (
                COUNT(DISTINCT v.person_id)
                - COUNT(DISTINCT CASE WHEN NOT ($notDisabled) THEN v.person_id END)
                - COUNT(DISTINCT CASE WHEN ($movedOutOrDead) THEN v.person_id END)
            ) AS total_must_pc,
            COUNT(DISTINCT CASE WHEN ($notDisabled) AND ($studyingAfterTHCS) THEN v.person_id END) AS studying_after_thcs
        ")->first();

        return [
            'studying' => (int)($result->studying_after_thcs ?? 0),
            'total' => max(0, (int)($result->total_must_pc ?? 0))
        ];
    }

    private function queryWardsMeetingSecondaryStandards(int $year, array $geoInfo): array
    {
        $academicYear = $year . '-' . ($year + 1);
        $years15_18 = [];
        for ($age = 15; $age <= 18; $age++) {
            $years15_18[] = $year - $age;
        }

        // Chỉ tính khi có province_id (cấp tỉnh)
        if (empty($geoInfo['province_id'])) {
            return ['level_1' => 0, 'level_2' => 0, 'level_3' => 0, 'total_wards' => 0];
        }

        // Lấy danh sách tất cả xã trong tỉnh với thông tin địa bàn khó khăn
        $wards = DB::table('geo_units as gu')
            ->join('geo_types as gt', 'gu.geo_type_id', '=', 'gt.id')
            ->where('gu.parent_id', $geoInfo['province_id'])
            ->where('gt.level', 3) // level 3 = commune/ward
            ->where('gu.scheme', 'new')
            ->select('gu.id', 'gu.is_difficult_area')
            ->get();

        $totalWards = count($wards);
        $level1Count = 0;
        $level2Count = 0;
        $level3Count = 0;

        // Tính mức độ đạt chuẩn cho từng xã
        foreach ($wards as $ward) {
            $wardId = $ward->id;
            $isDifficultArea = (bool)$ward->is_difficult_area;
            
            $qb = DB::table('v_survey_detail_flat as v')
                ->leftJoin('schools as s', 's.code', '=', 'v.school_code')
                ->where('v.academic_year', $academicYear)
                ->whereIn('v.dob_year', $years15_18)
                ->where('v.new_province_id', $geoInfo['province_id'])
                ->where('v.new_ward_id', $wardId);

            $notDisabled = "(
                COALESCE(v.dis_VAN_DONG,0)+COALESCE(v.dis_NGHE_NOI,0)+COALESCE(v.dis_NHIN,0)+
                COALESCE(v.dis_THAN_KINH,0)+COALESCE(v.dis_TRI_TUE,0)+COALESCE(v.dis_HOC_TAP,0)+
                COALESCE(v.dis_TU_KY,0)+COALESCE(v.dis_KHAC,0)
            ) = 0 AND v.dis_has_certificate=0";
            $movedOutOrDead = "UPPER(TRIM(v.sc_name)) IN ('CHẾT','DECEASED','CHUYỂN ĐI','CHUYEN DI','MOVED_OUT')";
            
            // Tốt nghiệp THCS - Logic từ R31Report
            $tnthcs = "(v.graduation_level_name = 'THCS' OR v.graduation_level_name = 'THPT')";
            
            $result = $qb->selectRaw("
                (
                    COUNT(DISTINCT v.person_id)
                    - COUNT(DISTINCT CASE WHEN NOT ($notDisabled) THEN v.person_id END)
                    - COUNT(DISTINCT CASE WHEN ($movedOutOrDead) THEN v.person_id END)
                ) AS total_must_pc,
                COUNT(DISTINCT CASE WHEN ($notDisabled) AND ($tnthcs) THEN v.person_id END) AS graduated_thcs
            ")->first();

            $totalMustPc = max(0, (int)($result->total_must_pc ?? 0));
            $graduatedThcs = (int)($result->graduated_thcs ?? 0);
            
            if ($totalMustPc > 0) {
                $ratio = $graduatedThcs / $totalMustPc;
                
                // Logic mới dựa trên điều kiện kinh tế - xã hội
                if ($isDifficultArea) {
                    // Xã có điều kiện kinh tế - xã hội đặc biệt khó khăn
                    if ($ratio >= 0.90) {
                        $level3Count++;
                    } elseif ($ratio >= 0.80) {
                        $level2Count++;
                    } elseif ($ratio >= 0.70) {
                        $level1Count++;
                    }
                } else {
                    // Xã bình thường
                    if ($ratio >= 0.95) {
                        $level3Count++;
                    } elseif ($ratio >= 0.90) {
                        $level2Count++;
                    } elseif ($ratio >= 0.80) {
                        $level1Count++;
                    }
                }
            }
        }

        return [
            'level_1' => $level1Count,
            'level_2' => $level2Count,
            'level_3' => $level3Count,
            'total_wards' => $totalWards
        ];
    }

    // === CÁC PHƯƠNG THỨC QUERY DỮ LIỆU XÓA MÙ CHỮ ===

    private function queryLiteracy15_25(int $year, array $geoInfo): array
    {
        $academicYear = $year . '-' . ($year + 1);
        $yMin = $year - 25; // 25 tuổi
        $yMax = $year - 15; // 15 tuổi

        // latest education theo năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academicYear);

        // latest literacy
        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id','=','d.person_id')
            ->leftJoinSub($peLatest,'pe', function($j){ $j->on('pe.person_id','=','p.id')->where('pe.rn',1); })
            ->leftJoin('education_blocks as eb','eb.id','=','pe.education_block_id')
            ->leftJoinSub($xmcLatest,'xmc', function($j){ $j->on('xmc.person_id','=','p.id')->where('xmc.rn',1); })
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academicYear);

        // áp địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('p.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('p.new_ward_id', $geoInfo['ward_id']);
        }

        // điều kiện
        $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";

        // chuẩn hoá "bỏ học lớp"
        $ebn  = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $grad = "(COALESCE(pe.graduation_level_id,0) < 2)";

        // Mức 1 (mù chữ mức độ 1) - theo báo cáo 5.3 và 5.9
        $lv1 = "(
            {$ageY}
            AND (
                ( {$grad} AND ({$ebn} <= 3) )
                OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1)
            )
        )";

        // Mức 2 (mù chữ mức độ 2) - theo báo cáo 5.3 và 5.9
        // Theo cong_thuc_baocao(5,37) = 1, nên dùng {$ebn} <= 5
        $lv2Drop = "({$ebn} <= 5)";
        $lv2 = "(
            {$ageY}
            AND (
                ( {$grad} AND {$lv2Drop} )
                OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2)
            )
        )";

        // Tổng dân số & các chỉ số trong dải tuổi 15-25
        $row = $qb->selectRaw("
                SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END)                 as total,
                SUM(CASE WHEN {$lv1} THEN 1 ELSE 0 END)                   as illiterate_level1,
                SUM(CASE WHEN {$lv2} THEN 1 ELSE 0 END)                   as illiterate_level2
            ")->first();

        $total = (int)($row->total ?? 0);
        $illiterateLevel1 = (int)($row->illiterate_level1 ?? 0);
        $illiterateLevel2 = (int)($row->illiterate_level2 ?? 0);
        
        // Tính toán biết chữ (theo báo cáo 5.9)
        $literateLevel1 = $total - $illiterateLevel1; // Đạt chuẩn biết chữ mức độ 1
        $literateLevel2 = $total - $illiterateLevel2; // Đạt chuẩn biết chữ mức độ 2
        $completelyIlliterate = $illiterateLevel1; // Mù chữ hoàn toàn = mù chữ mức độ 1

        return [
            'total' => $total,
            'literate_level1' => $literateLevel1,
            'literate_level2' => $literateLevel2,
            'illiterate_level1' => $illiterateLevel1,
            'illiterate_level2' => $illiterateLevel2,
            'completely_illiterate' => $completelyIlliterate
        ];
    }

    private function queryLiteracy15_35(int $year, array $geoInfo): array
    {
        $academicYear = $year . '-' . ($year + 1);
        $yMin = $year - 35; // 35 tuổi
        $yMax = $year - 15; // 15 tuổi

        // latest education theo năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academicYear);

        // latest literacy
        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id','=','d.person_id')
            ->leftJoinSub($peLatest,'pe', function($j){ $j->on('pe.person_id','=','p.id')->where('pe.rn',1); })
            ->leftJoin('education_blocks as eb','eb.id','=','pe.education_block_id')
            ->leftJoinSub($xmcLatest,'xmc', function($j){ $j->on('xmc.person_id','=','p.id')->where('xmc.rn',1); })
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academicYear);

        // áp địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('p.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('p.new_ward_id', $geoInfo['ward_id']);
        }

        // điều kiện
        $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";

        // chuẩn hoá "bỏ học lớp"
        $ebn  = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $grad = "(COALESCE(pe.graduation_level_id,0) < 2)";

        // Mức 1 (mù chữ mức độ 1) - theo báo cáo 5.3 và 5.9
        $lv1 = "(
            {$ageY}
            AND (
                ( {$grad} AND ({$ebn} <= 3) )
                OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1)
            )
        )";

        // Mức 2 (mù chữ mức độ 2) - theo báo cáo 5.3 và 5.9
        // Theo cong_thuc_baocao(5,37) = 1, nên dùng {$ebn} <= 5
        $lv2Drop = "({$ebn} <= 5)";
        $lv2 = "(
            {$ageY}
            AND (
                ( {$grad} AND {$lv2Drop} )
                OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2)
            )
        )";

        // Tổng dân số & các chỉ số trong dải tuổi 15-35
        $row = $qb->selectRaw("
                SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END)                 as total,
                SUM(CASE WHEN {$lv1} THEN 1 ELSE 0 END)                   as illiterate_level1,
                SUM(CASE WHEN {$lv2} THEN 1 ELSE 0 END)                   as illiterate_level2
            ")->first();

        $total = (int)($row->total ?? 0);
        $illiterateLevel1 = (int)($row->illiterate_level1 ?? 0);
        $illiterateLevel2 = (int)($row->illiterate_level2 ?? 0);
        
        // Tính toán biết chữ (theo báo cáo 5.9)
        $literateLevel1 = $total - $illiterateLevel1; // Đạt chuẩn biết chữ mức độ 1
        $literateLevel2 = $total - $illiterateLevel2; // Đạt chuẩn biết chữ mức độ 2
        $completelyIlliterate = $illiterateLevel1; // Mù chữ hoàn toàn = mù chữ mức độ 1

        return [
            'total' => $total,
            'literate_level1' => $literateLevel1,
            'literate_level2' => $literateLevel2,
            'illiterate_level1' => $illiterateLevel1,
            'illiterate_level2' => $illiterateLevel2,
            'completely_illiterate' => $completelyIlliterate
        ];
    }

    private function queryLiteracy15_60(int $year, array $geoInfo): array
    {
        $academicYear = $year . '-' . ($year + 1);
        $yMin = $year - 60; // 60 tuổi
        $yMax = $year - 15; // 15 tuổi

        // latest education theo năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academicYear);

        // latest literacy
        $xmcLatest = DB::table('person_literacy as x')
            ->select('x.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY x.person_id ORDER BY x.id DESC) rn'));

        $qb = DB::table('survey_form_details as d')
            ->join('survey_forms as f', function($j){
                $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false);
            })
            ->join('people as p', 'p.id','=','d.person_id')
            ->leftJoinSub($peLatest,'pe', function($j){ $j->on('pe.person_id','=','p.id')->where('pe.rn',1); })
            ->leftJoin('education_blocks as eb','eb.id','=','pe.education_block_id')
            ->leftJoinSub($xmcLatest,'xmc', function($j){ $j->on('xmc.person_id','=','p.id')->where('xmc.rn',1); })
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->where('d.is_deleted', false)
            ->where('d.academic_year', $academicYear);

        // áp địa bàn
        if (!empty($geoInfo['province_id'])) {
            $qb->where('p.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $qb->where('p.new_ward_id', $geoInfo['ward_id']);
        }

        // điều kiện
        $ageY  = "(p.dob_year BETWEEN {$yMin} AND {$yMax})";

        // chuẩn hoá "bỏ học lớp"
        $ebn  = "COALESCE(CAST(NULLIF(TRIM(pe.bo_hoc_lop),'') AS UNSIGNED),0)";
        $grad = "(COALESCE(pe.graduation_level_id,0) < 2)";

        // Mức 1 (mù chữ mức độ 1) - theo báo cáo 5.3 và 5.9
        $lv1 = "(
            {$ageY}
            AND (
                ( {$grad} AND ({$ebn} <= 3) )
                OR (xmc.hoan_thanh_lop = 3 OR xmc.tai_mu_chu_muc = 1)
            )
        )";

        // Mức 2 (mù chữ mức độ 2) - theo báo cáo 5.3 và 5.9
        // Theo cong_thuc_baocao(5,37) = 1, nên dùng {$ebn} <= 5
        $lv2Drop = "({$ebn} <= 5)";
        $lv2 = "(
            {$ageY}
            AND (
                ( {$grad} AND {$lv2Drop} )
                OR (xmc.hoan_thanh_lop = 5 OR xmc.tai_mu_chu_muc = 2)
            )
        )";

        // Tổng dân số & các chỉ số trong dải tuổi 15-60
        $row = $qb->selectRaw("
                SUM(CASE WHEN {$ageY} THEN 1 ELSE 0 END)                 as total,
                SUM(CASE WHEN {$lv1} THEN 1 ELSE 0 END)                   as illiterate_level1,
                SUM(CASE WHEN {$lv2} THEN 1 ELSE 0 END)                   as illiterate_level2
            ")->first();

        $total = (int)($row->total ?? 0);
        $illiterateLevel1 = (int)($row->illiterate_level1 ?? 0);
        $illiterateLevel2 = (int)($row->illiterate_level2 ?? 0);
        
        // Tính toán biết chữ (theo báo cáo 5.9)
        $literateLevel1 = $total - $illiterateLevel1; // Đạt chuẩn biết chữ mức độ 1
        $literateLevel2 = $total - $illiterateLevel2; // Đạt chuẩn biết chữ mức độ 2
        $completelyIlliterate = $illiterateLevel1; // Mù chữ hoàn toàn = mù chữ mức độ 1

        return [
            'total' => $total,
            'literate_level1' => $literateLevel1,
            'literate_level2' => $literateLevel2,
            'illiterate_level1' => $illiterateLevel1,
            'illiterate_level2' => $illiterateLevel2,
            'completely_illiterate' => $completelyIlliterate
        ];
    }

    private function queryLiteracyEnrollment(int $year, array $geoInfo): int
    {
        // Logic từ XMC614Report - tính số người huy động học xóa mù chữ trong năm
        $academicYear = $year . '-' . ($year + 1);
        
        // Logic: những người có bản ghi trong person_literacy (đã được huy động)
        $count = DB::table('person_literacy as pl')
            ->join('people as p', 'p.id', '=', 'pl.person_id')
            ->join('survey_form_details as d', function($j) use ($academicYear) {
                $j->on('d.person_id', '=', 'p.id')
                  ->where('d.academic_year', $academicYear)
                  ->where('d.is_deleted', false);
            })
            ->join('survey_forms as f', function($j) {
                $j->on('f.id', '=', 'd.survey_form_id')
                  ->where('f.is_deleted', false);
            });

        // áp địa bàn
        if (!empty($geoInfo['province_id'])) {
            $count->where('p.new_province_id', $geoInfo['province_id']);
        }
        if (!empty($geoInfo['ward_id'])) {
            $count->where('p.new_ward_id', $geoInfo['ward_id']);
        }

        return $count->count();
    }

    /**
     * Gán tất cả các placeholder thống kê vào Word template (tạm thời = 0)
     */
    private function setStatisticsPlaceholders(TemplateProcessor $templateProcessor): void
    {
        // === PHỔ CẬP GIÁO DỤC MẦM NON ===
        $templateProcessor->setValue('tre_5_tuoi_huy_dong_den_truong_so_luong', '0');
        $templateProcessor->setValue('tre_5_tuoi_huy_dong_den_truong_tong_so', '0');
        $templateProcessor->setValue('tre_5_tuoi_huy_dong_den_truong_ty_le', '0');
        
        $templateProcessor->setValue('tre_hoc_2_buoi_so_luong', '0');
        $templateProcessor->setValue('tre_hoc_2_buoi_tong_so', '0');
        $templateProcessor->setValue('tre_hoc_2_buoi_ty_le', '0');
        
        $templateProcessor->setValue('tre_5_tuoi_hoan_thanh_ct_so_luong', '0');
        $templateProcessor->setValue('tre_5_tuoi_hoan_thanh_ct_tong_so', '0');
        $templateProcessor->setValue('tre_5_tuoi_hoan_thanh_ct_ty_le', '0');
        
        $templateProcessor->setValue('tre_5_tuoi_khuyet_tat_tong_so', '0');
        $templateProcessor->setValue('tre_5_tuoi_khuyet_tat_co_kha_nang_hoc', '0');
        $templateProcessor->setValue('tre_5_tuoi_khuyet_tat_tiep_can_gd_so_luong', '0');
        $templateProcessor->setValue('tre_5_tuoi_khuyet_tat_tiep_can_gd_tong_so', '0');
        $templateProcessor->setValue('tre_5_tuoi_khuyet_tat_tiep_can_gd_ty_le', '0');
        $templateProcessor->setValue('tre_5_tuoi_khuyet_tat_hoc_noi_khac', '0');
        $templateProcessor->setValue('tre_5_tuoi_khuyet_tat_noi_khac_den_hoc', '0');

        // === PHỔ CẬP GIÁO DỤC TIỂU HỌC ===
        $templateProcessor->setValue('tre_6_tuoi_vao_lop_1_so_luong', '0');
        $templateProcessor->setValue('tre_6_tuoi_vao_lop_1_tong_so', '0');
        $templateProcessor->setValue('tre_6_tuoi_vao_lop_1_ty_le', '0');
        
        $templateProcessor->setValue('tre_den_14_tuoi_hoan_thanh_th_so_luong', '0');
        $templateProcessor->setValue('tre_den_14_tuoi_hoan_thanh_th_tong_so', '0');
        $templateProcessor->setValue('tre_den_14_tuoi_hoan_thanh_th_ty_le', '0');
        
        $templateProcessor->setValue('tre_11_tuoi_hoan_thanh_th_so_luong', '0');
        $templateProcessor->setValue('tre_11_tuoi_hoan_thanh_th_tong_so', '0');
        $templateProcessor->setValue('tre_11_tuoi_hoan_thanh_th_ty_le', '0');
        $templateProcessor->setValue('tre_11_tuoi_con_lai_hoc_th_so_luong', '0');
        $templateProcessor->setValue('tre_11_tuoi_con_lai_hoc_th_ty_le', '0');
        
        $templateProcessor->setValue('tre_khuyet_tat_tiep_can_gd_th_so_luong', '0');
        $templateProcessor->setValue('tre_khuyet_tat_tiep_can_gd_th_tong_so', '0');
        $templateProcessor->setValue('tre_khuyet_tat_tiep_can_gd_th_ty_le', '0');

        // === PHỔ CẬP GIÁO DỤC TRUNG HỌC ===
        $templateProcessor->setValue('thanh_thieu_nien_15_18_tot_nghiep_thcs_so_luong', '0');
        $templateProcessor->setValue('thanh_thieu_nien_15_18_tot_nghiep_thcs_tong_so', '0');
        $templateProcessor->setValue('thanh_thieu_nien_15_18_tot_nghiep_thcs_ty_le', '0');
        
        $templateProcessor->setValue('thanh_thieu_nien_15_18_dang_hoc_thpt_so_luong', '0');
        $templateProcessor->setValue('thanh_thieu_nien_15_18_dang_hoc_thpt_tong_so', '0');
        $templateProcessor->setValue('thanh_thieu_nien_15_18_dang_hoc_thpt_ty_le', '0');
        
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_1_so_luong', '0');
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_1_tong_so', '0');
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_1_ty_le', '0');
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_2_so_luong', '0');
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_2_tong_so', '0');
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_2_ty_le', '0');
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_3_so_luong', '0');
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_3_tong_so', '0');
        $templateProcessor->setValue('xa_dat_chuan_pcgd_thcs_muc_do_3_ty_le', '0');

        // === XÓA MÙ CHỮ ===
        // Dân số 15-25
        $templateProcessor->setValue('dan_so_15_25_tong_so', '0');
        $templateProcessor->setValue('dan_so_15_25_dat_chuan_biet_chu_muc_do_1_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_25_dat_chuan_biet_chu_muc_do_1_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_25_dat_chuan_biet_chu_muc_do_2_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_25_dat_chuan_biet_chu_muc_do_2_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_25_chua_dat_chuan_muc_do_1_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_25_chua_dat_chuan_muc_do_1_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_25_mu_chu_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_25_mu_chu_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_25_chua_dat_chuan_muc_do_2_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_25_chua_dat_chuan_muc_do_2_ty_le', '0');

        // Dân số 15-35
        $templateProcessor->setValue('dan_so_15_35_tong_so', '0');
        $templateProcessor->setValue('dan_so_15_35_dat_chuan_biet_chu_muc_do_1_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_35_dat_chuan_biet_chu_muc_do_1_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_35_dat_chuan_biet_chu_muc_do_2_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_35_dat_chuan_biet_chu_muc_do_2_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_35_chua_dat_chuan_muc_do_1_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_35_chua_dat_chuan_muc_do_1_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_35_mu_chu_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_35_mu_chu_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_35_chua_dat_chuan_muc_do_2_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_35_chua_dat_chuan_muc_do_2_ty_le', '0');

        // Dân số 15-60
        $templateProcessor->setValue('dan_so_15_60_tong_so', '0');
        $templateProcessor->setValue('dan_so_15_60_dat_chuan_biet_chu_muc_do_1_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_60_dat_chuan_biet_chu_muc_do_1_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_60_dat_chuan_biet_chu_muc_do_2_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_60_dat_chuan_biet_chu_muc_do_2_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_60_chua_dat_chuan_muc_do_1_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_60_chua_dat_chuan_muc_do_1_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_60_mu_chu_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_60_mu_chu_ty_le', '0');
        $templateProcessor->setValue('dan_so_15_60_chua_dat_chuan_muc_do_2_so_luong', '0');
        $templateProcessor->setValue('dan_so_15_60_chua_dat_chuan_muc_do_2_ty_le', '0');

        // Số người huy động học XMC
        $templateProcessor->setValue('so_nguoi_huy_dong_hoc_xoa_mu_chu', '0');
    }
}