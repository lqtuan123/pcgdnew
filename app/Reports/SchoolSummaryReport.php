<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Services\GeoService;
use App\Services\ReportFormatService;

class SchoolSummaryReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}

    /* ================= Template mapping ================= */
    private function templateCfg(): array
    {
        // Mẫu: 5.5.XMC.TruongTongHop.xlsx (đặt đúng tên file của bạn)
        return [
            'path' => resource_path('excel/4.4.THPT.TongHopTruong.xlsx'),
            'header' => [
                'province' => 'C2',
                'ward' => 'C3',
                'dateCell' => 'E4',
            ],
            'startRow' => 10,     // hàng đầu của danh sách xã/phường
            'lastCol'  => 'U',    // cột cuối của mẫu
            // cột dữ liệu theo mẫu trong ảnh (đánh số 1..20 trên tiêu đề chỉ để nhìn)
            'col' => [
                'stt' => 'A',
                'name'=> 'B',

                // --- TIỂU HỌC ---
                'pri_tot' => 'D',     // tổng số (cột 3)
                'pri_std' => 'E',     // số đạt chuẩn QG (cột 4)
                'pri_pct' => 'F',     // tỷ lệ % (cột 5)

                // --- THCS (kể cả nhiều cấp học đến lớp 9) ---
                  'thcs_all' => 'G', 
                'thcs_tot' => 'H',    // tổng số (cột 7)
                'thcs_std' => 'I',    // số đạt chuẩn QG (cột 8)
                'thcs_pct' => 'J',    // tỷ lệ % (cột 9)

                // trường nhiều cấp học (đến lớp 9)
                'multi9_tot' => 'K',  // tổng số (cột 11)
                'multi9_std' => 'L',  // số đạt chuẩn QG (cột 12)
                'multi9_pct' => 'M',  // tỷ lệ % (cột 13)

                // --- THPT (kể cả nhiều cấp học đến lớp 12) ---
                 'thpt_all' => 'N', 
                'thpt_tot' => 'O',    // tổng số (cột 15)
                'thpt_std' => 'P',    // số đạt chuẩn QG (cột 16)
                'thpt_pct' => 'Q',    // tỷ lệ % (cột 17)

                // trường nhiều cấp học (đến lớp 12)
                'multi12_tot' => 'R', // tổng số (cột 19)
                'multi12_std' => 'S', // số đạt chuẩn QG (cột 20)
                'multi12_pct' => 'T', // nếu mẫu bạn không có cột % thì bỏ map này

                // TT GDTX, TKTTH-HN, Cơ sở dạy nghề (đếm)
                'voc_tot' => 'U',     // cột ngoài cùng (nếu mẫu bạn đặt ở “T” => đổi lại)
            ],
            // hàng “Tổng” ở cuối bảng (mẫu đã có 1 dòng sẵn)
            'sumLabelCol' => 'B',
        ];
    }

    /* ================ Query gộp theo xã/phường trong 1 huyện ================ */
private function querySchoolsGrouped(string $scheme, array $geo): array
{
    // Nhóm bậc học
    $PRI      = [6];
    $THCS     = [2,7];
    $MULTI9   = [2];
    $THPT     = [3,4,8];
    $MULTI12  = [3,4];
    $VOC      = [1];

    // Cột địa bàn theo scheme
    $wardCol = $scheme === 'new' ? 's.new_ward_id'     : 's.old_ward_id';
    $provCol = $scheme === 'new' ? 's.new_province_id' : 's.old_province_id';

    $qb = DB::table('schools as s')
        ->where('s.is_dissolved', false)
        ->where('s.code', 'not like', 'NGOAI_TINH%');

    // --- Chế độ GROUP ---
    if (!empty($geo['ward_id'])) {
        // GROUP theo thôn/xóm trong xã
        $qb->where($wardCol, $geo['ward_id'])
           ->join('geo_units as g', 'g.id', '=', 's.village_id')   // tên thôn/xóm
           ->selectRaw('g.id AS gid, COALESCE(g.name,"") AS gname');
    } else {
        // GROUP theo xã trong tỉnh
        $qb->where($provCol, $geo['province_id'] ?? null)
           ->join('geo_units as g', 'g.id', '=', DB::raw($wardCol)) // tên xã/phường
           ->selectRaw('g.id AS gid, COALESCE(g.name,"") AS gname');
    }

    $rows = $qb->selectRaw("
            -- TH
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$PRI).")  THEN 1 ELSE 0 END) AS pri_tot,
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$PRI).")  AND s.is_datchuan=1 THEN 1 ELSE 0 END) AS pri_std,

            -- THCS + nhiều cấp đến lớp 9
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$THCS).") THEN 1 ELSE 0 END) AS thcs_tot,
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$THCS).") AND s.is_datchuan=1 THEN 1 ELSE 0 END) AS thcs_std,
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$MULTI9).") THEN 1 ELSE 0 END) AS multi9_tot,
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$MULTI9).") AND s.is_datchuan=1 THEN 1 ELSE 0 END) AS multi9_std,

            -- THPT + nhiều cấp đến lớp 12
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$THPT).") THEN 1 ELSE 0 END) AS thpt_tot,
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$THPT).") AND s.is_datchuan=1 THEN 1 ELSE 0 END) AS thpt_std,
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$MULTI12).") THEN 1 ELSE 0 END) AS multi12_tot,
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$MULTI12).") AND s.is_datchuan=1 THEN 1 ELSE 0 END) AS multi12_std,

            -- TT GDTX, TKTTH-HN, CSDN
            SUM(CASE WHEN s.educational_level_id IN (".implode(',',$VOC).") THEN 1 ELSE 0 END) AS voc_tot
        ")
        ->groupBy('gid','gname')
        ->orderBy('gname')
        ->get()
        ->map(fn($r)=>[
            'id'          => (int)$r->gid,
            'name'        => (string)$r->gname,
            'pri_tot'     => (int)$r->pri_tot,     'pri_std'     => (int)$r->pri_std,
            'thcs_tot'    => (int)$r->thcs_tot,    'thcs_std'    => (int)$r->thcs_std,
            'multi9_tot'  => (int)$r->multi9_tot,  'multi9_std'  => (int)$r->multi9_std,
            'thpt_tot'    => (int)$r->thpt_tot,    'thpt_std'    => (int)$r->thpt_std,
            'multi12_tot' => (int)$r->multi12_tot, 'multi12_std' => (int)$r->multi12_std,
            'voc_tot'     => (int)$r->voc_tot,
        ])
        ->all();

    return $rows;
}

    /* ============================== Export ============================== */
 public function export(Request $req)
{
    $scheme = (string)$req->input('scheme','old');
    $geo    = (array)$req->input('geo', []);

    $cfg = $this->templateCfg();
    $wb  = IOFactory::load($cfg['path']);
    $sh  = $wb->getActiveSheet();

    // Header
    $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
    $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
    $sh->setCellValue($cfg['header']['dateCell'], $this->formatService->top_date());
    $sh->getStyle('E4:K4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Lấy rows theo rule mới
    $rows  = $this->querySchoolsGrouped($scheme, $geo);

    // Chuẩn bị hàng
    $start = $cfg['startRow'];
    $need  = max(0, count($rows) - 1);
    if ($need > 0) {
        $sh->insertNewRowBefore($start + 1, $need);
        for ($i=1; $i<=$need; $i++) {
            $this->replicateRowFormatMerged($sh, $start, $start+$i, $cfg['lastCol']);
        }
    }

    $C   = $cfg['col'];
    $r   = $start;
    $stt = 1;

    // Bộ cộng dồn (dùng khi có nhiều dòng)
    $sum = [
        'pri_tot'=>0,'pri_std'=>0,
        'thcs_tot'=>0,'thcs_std'=>0,
        'multi9_tot'=>0,'multi9_std'=>0,
        'thpt_tot'=>0,'thpt_std'=>0,
        'multi12_tot'=>0,'multi12_std'=>0,
        'voc_tot'=>0,
    ];
    // dd($rows);
    foreach ($rows as $row) {
        $sh->setCellValue($C['stt'].$r, $stt++);
        $sh->setCellValue($C['name'].$r, $row['name'] ?: '—');
            $sh->mergeCells('B'.$r.':C'.$r);

        $sh->getStyle('B'.$r.':C'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
  
        $w   = fn(string $col, int $v) => $sh->setCellValue($col.$r, $v ?: null);
        $pct = fn(string $pctCol, string $totCol, string $stdCol)
            => $sh->setCellValue($pctCol.$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $totCol, $r, $stdCol));

        // TH
        $w($C['pri_tot'], $row['pri_tot']);   $w($C['pri_std'], $row['pri_std']);   $pct($C['pri_pct'],$C['pri_tot'],$C['pri_std']);
        // THCS
        $w($C['thcs_tot'],$row['thcs_tot']);  $w($C['thcs_std'],$row['thcs_std']);  $pct($C['thcs_pct'],$C['thcs_tot'],$C['thcs_std']);
        // Nhiều cấp đến 9
        $w($C['multi9_tot'],$row['multi9_tot']); $w($C['multi9_std'],$row['multi9_std']); $pct($C['multi9_pct'],$C['multi9_tot'],$C['multi9_std']);
        // THPT
        $w($C['thpt_tot'],$row['thpt_tot']);  $w($C['thpt_std'],$row['thpt_std']);  $pct($C['thpt_pct'],$C['thpt_tot'],$C['thpt_std']);
        // Nhiều cấp đến 12
        $w($C['multi12_tot'],$row['multi12_tot']); $w($C['multi12_std'],$row['multi12_std']);
        if (isset($C['multi12_pct'])) $pct($C['multi12_pct'],$C['multi12_tot'],$C['multi12_std']);
        // GDTX/CSDN
        $w($C['voc_tot'],$row['voc_tot']);

        // Tổng khối trong hàng
        $sh->setCellValue($C['thcs_all'].$r, sprintf('=IF(SUM(%1$s%2$d,%3$s%2$d)=0,"",%1$s%2$d+%3$s%2$d)', $C['thcs_tot'],$r,$C['multi9_tot']));
        $sh->setCellValue($C['thpt_all'].$r, sprintf('=IF(SUM(%1$s%2$d,%3$s%2$d)=0,"",%1$s%2$d+%3$s%2$d)', $C['thpt_tot'],$r,$C['multi12_tot']));

        // Cộng dồn
        foreach ($sum as $k=>$_) $sum[$k] += $row[$k];

        $r++;
    }

    // Hàng TỔNG: chỉ hiện khi >1 dòng (tức group ra nhiều đơn vị)
    if (count($rows) > 1) {
        $sh->setCellValue($cfg['sumLabelCol'].$r, 'Tổng');

        $wSum  = fn(string $col, int $v) => $sh->setCellValue($col.$r, $v ?: null);
        $pctSm = fn(string $pctCol, string $totCol, string $stdCol)
            => $sh->setCellValue($pctCol.$r,
                sprintf('=IF(%1$s%2$d=0,"",ROUND(%3$s%2$d/%1$s%2$d*100,2))', $totCol, $r, $stdCol));

        $wSum($C['pri_tot'],$sum['pri_tot']);     $wSum($C['pri_std'],$sum['pri_std']);     $pctSm($C['pri_pct'],$C['pri_tot'],$C['pri_std']);
        $wSum($C['thcs_tot'],$sum['thcs_tot']);   $wSum($C['thcs_std'],$sum['thcs_std']);   $pctSm($C['thcs_pct'],$C['thcs_tot'],$C['thcs_std']);
        $wSum($C['multi9_tot'],$sum['multi9_tot']); $wSum($C['multi9_std'],$sum['multi9_std']); $pctSm($C['multi9_pct'],$C['multi9_tot'],$C['multi9_std']);
        $wSum($C['thpt_tot'],$sum['thpt_tot']);   $wSum($C['thpt_std'],$sum['thpt_std']);   $pctSm($C['thpt_pct'],$C['thpt_tot'],$C['thpt_std']);
        $wSum($C['multi12_tot'],$sum['multi12_tot']); $wSum($C['multi12_std'],$sum['multi12_std']);
        if (isset($C['multi12_pct'])) $pctSm($C['multi12_pct'],$C['multi12_tot'],$C['multi12_std']);
        $wSum($C['voc_tot'],$sum['voc_tot']);

        // Tổng khối tại hàng Tổng
        $sh->setCellValue($C['thcs_all'].$r, sprintf('=IF(SUM(%1$s%2$d,%3$s%2$d)=0,"",%1$s%2$d+%3$s%2$d)', $C['thcs_tot'],$r,$C['multi9_tot']));
        $sh->setCellValue($C['thpt_all'].$r, sprintf('=IF(SUM(%1$s%2$d,%3$s%2$d)=0,"",%1$s%2$d+%3$s%2$d)', $C['thpt_tot'],$r,$C['multi12_tot']));
    }

    // Định dạng %
    foreach ([$C['pri_pct'],$C['thcs_pct'],$C['multi9_pct'],$C['thpt_pct']] as $pctCol) {
        $sh->getStyle($pctCol.$cfg['startRow'].':'.$pctCol.$r)->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
    }
    if (isset($C['multi12_pct'])) {
        $sh->getStyle($C['multi12_pct'].$cfg['startRow'].':'.$C['multi12_pct'].$r)->getNumberFormat()->setFormatCode('0.00;-0.00;;@');
    }
     $lastRow = $cfg['startRow']+  $need  + 3;
    $this->setCellValue($sh, 'P'.$lastRow, $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null)));
    $sh->mergeCells('P'.$lastRow.':T'.$lastRow);
    $sh->getStyle('P'.$lastRow.':T'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    return response()->streamDownload(function () use ($wb) {
        $writer = IOFactory::createWriter($wb, 'Xlsx');
        $writer->setPreCalculateFormulas(false);
        $writer->save('php://output');
    }, $this->getExportFilename($cfg['path']), [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}


}
