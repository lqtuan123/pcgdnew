<?php
// app/Http/Controllers/Front/ReligionController.php
namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Religion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class ReligionController extends Controller
{
    public function index()
    {
        // Kiểm tra quyền quản lý tôn giáo
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_ton_giao', 'view')) {
            abort(403, 'Bạn không có quyền xem danh mục tôn giáo.');
        }
        
        return view('front.religions.index');
    }

    public function data(Request $req)
    {
        // Kiểm tra quyền quản lý tôn giáo
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_ton_giao', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem danh mục tôn giáo.'], 403);
        }
        
        $q        = trim((string)$req->input('q'));
        $official = $req->input('official', 'all');   // all|only|exclude
        $perPage  = max(5, min(200, (int)$req->input('per_page', 20)));

        $p = Religion::query()
            ->when($q, fn($qr)=>$qr->where(fn($w)=>$w
                ->where('name','like',"%{$q}%")
                ->orWhere('code','like',"%{$q}%")
                ->orWhere('slug','like',"%{$q}%")
            ))
            ->when($official === 'only',    fn($qr)=>$qr->where('is_official', 1))
            ->when($official === 'exclude', fn($qr)=>$qr->where('is_official', 0))
            ->orderBy('name')
            ->paginate($perPage);

        // chỉ trả về 4 trường cần dùng
        $p->getCollection()->transform(fn($r)=>[
            'id'          => $r->id,
            'code'        => $r->code,
            'name'        => $r->name,
            'is_official' => (bool)$r->is_official,
            'note'        => $r->note,
        ]);

        return response()->json($p);
    }

   public function export(Request $req)
    {
        // Kiểm tra quyền quản lý tôn giáo
        $user = Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_ton_giao', 'view')) {
            abort(403, 'Bạn không có quyền xuất danh mục tôn giáo.');
        }
        
        $q        = trim((string)$req->input('q'));
        $official = $req->input('official', 'all'); // all|only|exclude

        $rows = Religion::query()
            ->when($q, fn($qr)=>$qr->where(fn($w)=>$w
                ->where('name','like',"%{$q}%")
                ->orWhere('code','like',"%{$q}%")
                ->orWhere('slug','like',"%{$q}%")
            ))
            ->when($official === 'only',    fn($qr)=>$qr->where('is_official', 1))
            ->when($official === 'exclude', fn($qr)=>$qr->where('is_official', 0))
            ->orderBy('name')
            ->get(['code','name','is_official','note']);

        // ====== Spreadsheet ======
        $x = new Spreadsheet();
        $sh = $x->getActiveSheet();
        $sh->setTitle('Tôn giáo');

        // Tiêu đề
        $sh->mergeCells('A1:E1');
        $sh->setCellValue('A1', 'DANH MỤC TÔN GIÁO');
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Dòng thông tin phụ
        $sh->setCellValue('A2', 'Xuất lúc: ' . now()->format('d/m/Y H:i'));
        $sh->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        // Header
        $sh->fromArray(['STT','Mã','Tên tôn giáo','Trạng thái','Ghi chú'], null, 'A3');

        // Kích thước cột
        $sh->getColumnDimension('A')->setWidth(6);
        $sh->getColumnDimension('B')->setWidth(18);
        $sh->getColumnDimension('C')->setWidth(40);
        $sh->getColumnDimension('D')->setWidth(20);
        $sh->getColumnDimension('E')->setWidth(60);

        // Đổ dữ liệu
        $r = 4;
        foreach ($rows as $i => $row) {
            $sh->setCellValue("A{$r}", $i + 1);
            $sh->setCellValue("B{$r}", $row->code);
            $sh->setCellValue("C{$r}", $row->name);
            $sh->setCellValue("D{$r}", $row->is_official ? 'Được công nhận' : 'Khác');
            $sh->setCellValue("E{$r}", (string)$row->note);
            $r++;
        }
        $last = max(3, $r - 1);

        // Style header
        $sh->getStyle("A3:E3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']], // xanh dương
        ]);
        $sh->getRowDimension(3)->setRowHeight(22);

        // Viền bảng
        $sh->getStyle("A3:E{$last}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCBD5E1'], // slate-300
                ],
            ],
        ]);

        // Zebra rows + wrap ghi chú
        if ($last >= 4) {
            for ($i = 4; $i <= $last; $i += 2) {
                $sh->getStyle("A{$i}:E{$i}")
                   ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC'); // slate-50
            }
            $sh->getStyle("E4:E{$last}")->getAlignment()->setWrapText(true);
        }

        // Căn lề cho các cột
        $sh->getStyle("A4:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("B4:B{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sh->getStyle("C4:C{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sh->getStyle("D4:D{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Freeze header
        $sh->freezePane('A4');

        // Xuất file
        $file = 'ton_giao_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($x);

        return response()->streamDownload(function () use ($writer) {
            // tránh rò byte BOM/ob
            if (ob_get_length()) { ob_end_clean(); }
            $writer->save('php://output');
        }, $file, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

}
