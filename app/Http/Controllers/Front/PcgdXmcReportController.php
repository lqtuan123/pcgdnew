<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\PcgdReportDraft;
use App\Services\GeoService;
use App\Services\PcgdXmcReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PcgdXmcReportController extends Controller
{
    public function __construct(
        private GeoService $geoService,
        private PcgdXmcReportExportService $exportService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Lấy thông tin địa phương từ user
        $geo = [
            'province_id' => $user?->province_id,
            'ward_id' => $user?->ward_id,
            'village_id' => null
        ];

        // Lấy tên địa phương để hiển thị
        $provinceName = $this->geoService->geoNameFromId($geo['province_id']);
        $wardName = $this->geoService->geoNameFromId($geo['ward_id']);

        // Load draft nếu có
        $draftData = null;
        if ($user) {
            $draft = PcgdReportDraft::where('user_id', $user->id)
                ->where('report_type', 'bien_ban_pcgd_xmc')
                ->first();
            
            if ($draft) {
                $draftData = $draft->data ?? [];
            }
        }

        return view('front.pcgd-xmc-report.index', compact('geo', 'provinceName', 'wardName', 'draftData'));
    }

    public function export(Request $request)
    {
        $request->validate([
            'academic_year' => 'required|string',
            'uu_diem' => 'nullable|string',
            'nhuoc_diem' => 'nullable|string',
            'de_xuat_kien_nghi' => 'nullable|string',
            // Điều kiện đảm bảo
            'so_nguoi_huy_dong_hoc_xoa_mu_chu' => 'nullable|string',
            'don_vi_khong_dat_chuan_xoa_mu_chu' => 'nullable|string',
            'co_so_vat_chat_mam_non' => 'nullable|string',
            'co_so_vat_chat_tieu_hoc' => 'nullable|string',
            'co_so_vat_chat_trung_hoc_co_so' => 'nullable|string',
            'co_so_vat_chat_trung_tam_gdnn_gdtx' => 'nullable|string',
            'co_so_vat_chat_trung_tam_hoc_tap_cong_dong' => 'nullable|string',
            'kinh_phi_pho_cap_giao_duc' => 'nullable|string',
            'kinh_phi_xoa_mu_chu' => 'nullable|string',
            'nguoi_tham_gia_mam_non' => 'nullable|string',
            'nguoi_tham_gia_tieu_hoc' => 'nullable|string',
            'nguoi_tham_gia_trung_hoc_co_so' => 'nullable|string',
            'nguoi_tham_gia_trung_tam_gdnn_gdtx' => 'nullable|string',
            'nguoi_tham_gia_trung_tam_hoc_tap_cong_dong' => 'nullable|string',
        ]);

        $user = Auth::user();
        
        // Dữ liệu từ CKEditor và form
        $data = [
            'uu_diem' => $request->input('uu_diem', ''),
            'nhuoc_diem' => $request->input('nhuoc_diem', ''),
            'de_xuat_kien_nghi' => $request->input('de_xuat_kien_nghi', ''),
            // Điều kiện đảm bảo
            'so_nguoi_huy_dong_hoc_xoa_mu_chu' => $request->input('so_nguoi_huy_dong_hoc_xoa_mu_chu', ''),
            'don_vi_khong_dat_chuan_xoa_mu_chu' => $request->input('don_vi_khong_dat_chuan_xoa_mu_chu', ''),
            'co_so_vat_chat_mam_non' => $request->input('co_so_vat_chat_mam_non', ''),
            'co_so_vat_chat_tieu_hoc' => $request->input('co_so_vat_chat_tieu_hoc', ''),
            'co_so_vat_chat_trung_hoc_co_so' => $request->input('co_so_vat_chat_trung_hoc_co_so', ''),
            'co_so_vat_chat_trung_tam_gdnn_gdtx' => $request->input('co_so_vat_chat_trung_tam_gdnn_gdtx', ''),
            'co_so_vat_chat_trung_tam_hoc_tap_cong_dong' => $request->input('co_so_vat_chat_trung_tam_hoc_tap_cong_dong', ''),
            'kinh_phi_pho_cap_giao_duc' => $request->input('kinh_phi_pho_cap_giao_duc', ''),
            'kinh_phi_xoa_mu_chu' => $request->input('kinh_phi_xoa_mu_chu', ''),
            'nguoi_tham_gia_mam_non' => $request->input('nguoi_tham_gia_mam_non', ''),
            'nguoi_tham_gia_tieu_hoc' => $request->input('nguoi_tham_gia_tieu_hoc', ''),
            'nguoi_tham_gia_trung_hoc_co_so' => $request->input('nguoi_tham_gia_trung_hoc_co_so', ''),
            'nguoi_tham_gia_trung_tam_gdnn_gdtx' => $request->input('nguoi_tham_gia_trung_tam_gdnn_gdtx', ''),
            'nguoi_tham_gia_trung_tam_hoc_tap_cong_dong' => $request->input('nguoi_tham_gia_trung_tam_hoc_tap_cong_dong', ''),
        ];

        // Thông tin địa phương và năm học
        $geoData = $request->input('geo', []);
        
        // Kiểm tra nếu thiếu thông tin tỉnh
        if (empty($geoData['province_id'])) {
            return back()->with('error', 'Thiếu thông tin tỉnh/thành phố');
        }
        
        $geoInfo = [
            'province_id' => $geoData['province_id'] ?? null,
            'ward_id' => $geoData['ward_id'] ?? null,
            'province_name' => $this->geoService->geoNameFromId($geoData['province_id'] ?? null),
            'ward_name' => $this->geoService->geoNameFromId($geoData['ward_id'] ?? null),
            'academic_year' => $request->input('academic_year'),
        ];

        // Xuất báo cáo
        $result = $this->exportService->export($data, $geoInfo);

        if ($result['success']) {
            return redirect()->route('front.pcgd-xmc-report.download', ['filename' => $result['filename']]);
        } else {
            return back()->with('error', 'Lỗi khi xuất biên bản: ' . $result['error']);
        }
    }

    public function saveDraft(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Bạn cần đăng nhập để lưu bản nháp'], 401);
        }

        // Lấy tất cả dữ liệu form (trừ các trường không cần lưu)
        $draftData = $request->except(['_token', 'academic_year', 'geo']);

        // Tìm hoặc tạo draft
        $draft = PcgdReportDraft::updateOrCreate(
            [
                'user_id' => $user->id,
                'report_type' => 'bien_ban_pcgd_xmc',
            ],
            [
                'data' => $draftData,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu thông tin thành công',
            'updated_at' => $draft->updated_at->format('d/m/Y H:i:s'),
        ]);
    }

    public function download(string $filename): BinaryFileResponse
    {
        $filePath = storage_path('app/exports/' . $filename);
        
        if (!file_exists($filePath)) {
            abort(404, 'File không tồn tại');
        }

        return response()->download($filePath, 'bien_ban_pcgd_xmc_' . date('Y-m-d') . '.docx');
    }
}
