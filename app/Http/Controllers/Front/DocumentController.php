<?php
// app/Http/Controllers/Front/DocumentController.php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Issuer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DocumentController extends Controller
{
    public function index(Request $req)
    {
        // Filter options
        $issuers  = Issuer::orderBy('name')->get(['id','name']);
        $statuses = Document::query()->select('status')->distinct()->pluck('status')->filter()->values();

        return view('front.documents.index', compact('issuers','statuses'));
    }

    public function data(Request $req)
    {
        $q         = trim((string) $req->input('q'));
        $issuerId  = $req->input('issuer_id');
        $status    = $req->input('status');
        $dateFrom  = $req->input('issued_from'); // Y-m-d
        $dateTo    = $req->input('issued_to');   // Y-m-d
        $perPage   = (int)($req->input('per_page', 20));
        $perPage   = max(10, min(100, $perPage));

        $query = Document::query()
            ->with('issuer:id,name')
            ->when($q, function($qr) use ($q){
                $qr->where(function($w) use ($q){
                    $w->where('title','like',"%{$q}%")
                      ->orWhere('code','like',"%{$q}%");
                });
            })
            ->when($issuerId, fn($qr)=>$qr->where('issuer_id', $issuerId))
            ->when($status !== null && $status !== '', fn($qr)=>$qr->where('status', $status))
            ->when($dateFrom, function($qr) use ($dateFrom){
                $d = Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
                $qr->whereDate('issued_date', '>=', $d);
            })
            ->when($dateTo, function($qr) use ($dateTo){
                $d = Carbon::createFromFormat('Y-m-d', $dateTo)->endOfDay();
                $qr->whereDate('issued_date', '<=', $d);
            })
            ->orderByDesc('issued_date')
            ->orderBy('title');

        $pag = $query->paginate($perPage);

        // Map dữ liệu cho frontend (dùng id làm key để tránh cảnh báo Alpine)
        $data = $pag->getCollection()->map(function (Document $d) {
            return [
                'id'          => $d->id,
                'code'        => $d->code,
                'title'       => $d->title,
                'issued_date' => optional($d->issued_date)->format('d/m/Y'),
                'issuer'      => $d->issuer ? ['id'=>$d->issuer->id, 'name'=>$d->issuer->name] : null,
                'status'      => (string)$d->status,
              
                'file_url'    => $d->file_path ? Storage::url($d->file_path) : null,
            ];
        });

        return response()->json([
            'data'         => $data,
            'current_page' => $pag->currentPage(),
            'last_page'    => $pag->lastPage(),
            'per_page'     => $pag->perPage(),
            'total'        => $pag->total(),
        ]);
    }

    public function export(Request $req)
    {
        $q         = trim((string) $req->input('q'));
        $issuerId  = $req->input('issuer_id');
        $status    = $req->input('status');
        $dateFrom  = $req->input('issued_from'); // Y-m-d
        $dateTo    = $req->input('issued_to');   // Y-m-d

        $rows = Document::query()
            ->with('issuer:id,name')
            ->when($q, fn($qr)=>$qr->where(fn($w)=>$w->where('title','like',"%{$q}%")->orWhere('code','like',"%{$q}%")))
            ->when($issuerId, fn($qr)=>$qr->where('issuer_id',$issuerId))
            ->when($status !== null && $status !== '', fn($qr)=>$qr->where('status',$status))
            ->when($dateFrom, function($qr) use ($dateFrom){
                $d = Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
                $qr->whereDate('issued_date', '>=', $d);
            })
            ->when($dateTo, function($qr) use ($dateTo){
                $d = Carbon::createFromFormat('Y-m-d', $dateTo)->endOfDay();
                $qr->whereDate('issued_date', '<=', $d);
            })
            ->orderByDesc('issued_date')
            ->orderBy('title')
            ->get(['id','code','title','issued_date','issuer_id','file_path','status']);

        // ===== Excel đẹp =====
        $x  = new Spreadsheet();
        $sh = $x->getActiveSheet();
        $sh->setTitle('Tài liệu');

        // Tiêu đề
        $sh->mergeCells('A1:H1');
        $sh->setCellValue('A1', 'DANH MỤC TÀI LIỆU');
        $sh->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sh->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sh->setCellValue('A2', 'Xuất lúc: '.now()->format('d/m/Y H:i'));
        $sh->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        // Header
        $sh->fromArray(['STT','Mã','Tiêu đề','Ngày ban hành','Cơ quan ban hành','Trạng thái'], null, 'A3');

        // Width
        $sh->getColumnDimension('A')->setWidth(6);
        $sh->getColumnDimension('B')->setWidth(18);
        $sh->getColumnDimension('C')->setWidth(50);
        $sh->getColumnDimension('D')->setWidth(18);
        $sh->getColumnDimension('E')->setWidth(32);
        $sh->getColumnDimension('F')->setWidth(18);
       

        // Data
        $r = 4;
        foreach ($rows as $i => $row) {
            $sh->setCellValue("A{$r}", $i+1);
            $sh->setCellValue("B{$r}", $row->code);
            $sh->setCellValue("C{$r}", $row->title);
            $sh->setCellValue("D{$r}", optional($row->issued_date)->format('d/m/Y'));
            $sh->setCellValue("E{$r}", optional($row->issuer)->name ?? '');
            $sh->setCellValue("F{$r}", (string)$row->status);
         
            $r++;
        }
        $last = max(3, $r-1);

        // Header style
        $sh->getStyle("A3:F3")->applyFromArray([
            'font' => ['bold'=>true, 'color'=>['argb'=>'FFFFFFFF']],
            'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER, 'vertical'=>Alignment::VERTICAL_CENTER],
            'fill' => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['argb'=>'FF0EA5E9']], // cyan-600
        ]);
        $sh->getRowDimension(3)->setRowHeight(22);

        // Borders
        $sh->getStyle("A3:H{$last}")->applyFromArray([
            'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['argb'=>'FFCBD5E1']]],
        ]);

        // Zebra + wrap text
        if ($last >= 4) {
            for ($i=4; $i<=$last; $i+=2) {
                $sh->getStyle("A{$i}:H{$i}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC'); // slate-50
            }
            $sh->getStyle("C4:C{$last}")->getAlignment()->setWrapText(true);
            $sh->getStyle("G4:G{$last}")->getAlignment()->setWrapText(true);
        }

        // Align
        $sh->getStyle("A4:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("B4:B{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sh->getStyle("D4:D{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("F4:F{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Freeze
        $sh->freezePane('A4');

        // Stream
        $file = 'tai_lieu_'.now()->format('Ymd_His').'.xlsx';
        $writer = new Xlsx($x);

        return response()->streamDownload(function() use ($writer){
            if (ob_get_length()) { ob_end_clean(); }
            $writer->save('php://output');
        }, $file, ['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
