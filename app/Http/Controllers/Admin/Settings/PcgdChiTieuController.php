<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Models\ChuongTrinh;
use App\Models\ChiTieu;
use App\Models\ChiTieuGiaTri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PcgdChiTieuController extends Controller
{
    /**
     * Hiển thị form chọn chương trình và danh sách chỉ tiêu
     */
    public function index(Request $request)
    {
        // Lấy tất cả chương trình
        $chuongTrinhs = ChuongTrinh::active()->get();
        
        $selectedChuongTrinh = null;
        $chiTieuGiaTris = collect();

        // Nếu chọn chương trình
        if ($request->filled('chuong_trinh_id')) {
            $selectedChuongTrinh = ChuongTrinh::with('chiTieuGiaTris.chiTieu')->findOrFail($request->chuong_trinh_id);
            
            // Lấy các chỉ tiêu đã được gắn vào chương trình này
            $chiTieuGiaTris = $selectedChuongTrinh->chiTieuGiaTris;
        }

        return view('admin.settings.pcgd-chi-tieu.index', compact(
            'chuongTrinhs',
            'selectedChuongTrinh',
            'chiTieuGiaTris'
        ));
    }

    /**
     * Lưu/cập nhật hàng loạt giá trị chỉ tiêu
     * Hỗ trợ format số theo Việt Nam (70,00) hoặc Quốc tế (70.00)
     */
    public function update(Request $request)
    {
        // Parse giá trị từ format hiển thị về số thực
        $chiTieus = $request->input('chi_tieus', []);
        foreach ($chiTieus as $index => &$data) {
            if (isset($data['khong_kk_dbkk'])) {
                $data['khong_kk_dbkk'] = $this->parseDecimal($data['khong_kk_dbkk']);
            }
            if (isset($data['vung_kk_dbkk'])) {
                $data['vung_kk_dbkk'] = $this->parseDecimal($data['vung_kk_dbkk']);
            }
        }
        $request->merge(['chi_tieus' => $chiTieus]);

        $request->validate([
            'chuong_trinh_id' => 'required|exists:chuong_trinh,id',
            'chi_tieus' => 'required|array',
            'chi_tieus.*.chi_tieu_id' => 'required|exists:chi_tieu,id',
            'chi_tieus.*.khong_kk_dbkk' => 'nullable|numeric|min:0|max:100',
            'chi_tieus.*.vung_kk_dbkk' => 'nullable|numeric|min:0|max:100',
        ], [
            'chuong_trinh_id.required' => 'Vui lòng chọn chương trình',
            'chi_tieus.*.khong_kk_dbkk.numeric' => 'Giá trị phải là số',
            'chi_tieus.*.khong_kk_dbkk.max' => 'Giá trị không được vượt quá 100',
            'chi_tieus.*.vung_kk_dbkk.numeric' => 'Giá trị phải là số',
            'chi_tieus.*.vung_kk_dbkk.max' => 'Giá trị không được vượt quá 100',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->chi_tieus as $data) {
                ChiTieuGiaTri::updateOrCreate(
                    [
                        'chuong_trinh_id' => $request->chuong_trinh_id,
                        'chi_tieu_id' => $data['chi_tieu_id'],
                    ],
                    [
                        'khong_kk_dbkk' => $data['khong_kk_dbkk'] ?? null,
                        'vung_kk_dbkk' => $data['vung_kk_dbkk'] ?? null,
                    ]
                );
            }

            DB::commit();

            return redirect()->route('admin.settings.pcgd-chi-tieu.index', [
                'chuong_trinh_id' => $request->chuong_trinh_id
            ])->with('success', 'Đã cập nhật giá trị chỉ tiêu thành công');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Có lỗi xảy ra: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Parse số thập phân từ format hiển thị (70,00 hoặc 70.00) về số thực (70.00)
     * Hỗ trợ cả format Việt Nam (dấu phẩy) và Quốc tế (dấu chấm)
     */
    private function parseDecimal($value)
    {
        if (empty($value)) {
            return null;
        }

        // Loại bỏ khoảng trắng
        $value = trim($value);
        
        // Đếm số lần xuất hiện của dấu chấm và dấu phẩy
        $dotCount = substr_count($value, '.');
        $commaCount = substr_count($value, ',');
        
        // Xác định format dựa vào vị trí dấu cuối cùng
        if ($commaCount > 0 && $dotCount > 0) {
            // Có cả 2 dấu → xác định dấu nào là thập phân
            $lastCommaPos = strrpos($value, ',');
            $lastDotPos = strrpos($value, '.');
            
            if ($lastCommaPos > $lastDotPos) {
                // Format VN: 1.234,56 → bỏ dấu chấm, giữ dấu phẩy
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                // Format Quốc tế: 1,234.56 → bỏ dấu phẩy, giữ dấu chấm
                $value = str_replace(',', '', $value);
            }
        } elseif ($commaCount > 0) {
            // Chỉ có dấu phẩy → Format VN: 70,00
            $value = str_replace(',', '.', $value);
        }
        // Nếu chỉ có dấu chấm → giữ nguyên (Format Quốc tế hoặc số nguyên lớn)
        
        return is_numeric($value) ? (float) $value : null;
    }
}

