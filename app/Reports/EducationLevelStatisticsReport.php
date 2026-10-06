<?php

namespace App\Reports;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use App\Services\GeoService;
use App\Services\ReportFormatService;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill; // <-- thêm dòng này
class EducationLevelStatisticsReport extends BaseReport
{
    public function __construct(
        protected GeoService $geoService,
        protected ReportFormatService $formatService,
    ) {}


    /* ====================== CONFIG TEMPLATE ====================== */
    private function templateCfg(): array
{
    return [
        'path'      => resource_path('excel/5.10.TrinhDoVanHoa.xlsx'),
        'header'    => [
            'province' => 'C1',
            'ward'     => 'C2',
            'year'     => 'C4',
            'dateCell' => 'G4',
        ],
        'startRow'  => 9,
        'lastCol'   => 'AM',
        'col' => [
            'dob' => 'A', 'age' => 'B', 'tot' => 'C', 'f' => 'E', 'eth' => 'D', 'disabled' => 'F',

            // ====== ĐI HỌC: Tổng đi học + Mầm non + lớp 1..12 ======
            'stu_tot' => 'G',          // Tổng đi học
            'stu_pre' => 'H',          // Tổng mầm non
            'stu_1'   => 'I','stu_2'=>'J','stu_3'=>'K','stu_4'=>'L','stu_5'=>'M','stu_6'=>'N',
            'stu_7'   => 'O','stu_8'=>'P','stu_9'=>'Q','stu_10'=>'R','stu_11'=>'S','stu_12'=>'T',

            // ====== TỐT NGHIỆP ======
            'grad_th'   => 'U',
            'grad_thcs' => 'V',
            'grad_plus' => 'W',
            // 'grad_tot'  => 'X',

            // ====== BỎ HỌC: Tổng bỏ học + Không đi học + bỏ theo lớp 1..12 ======
            'drop_tot'  => 'X',        // Tổng bỏ học
            'never'     => 'Y',        // Không đi học
            'drop_1'    => 'Z','drop_2'=>'AA','drop_3'=>'AB','drop_4'=>'AC','drop_5'=>'AD','drop_6'=>'AE',
            'drop_7'    => 'AD','drop_8'=>'AG','drop_9'=>'AH','drop_10'=>'AI','drop_11'=>'AJ','drop_12'=>'AK',

            // Tổng phụ: bỏ học nhưng đã tốt nghiệp …
            'drop_grad_th'   => 'AL',
            'drop_grad_thcs' => 'AM',
        ],
    ];
}

    /* =============== TÊN LỚP “MẦM NON” để nhận diện =============== */
    private array $preschoolNames = [
        '3-12 tháng','13-24 tháng','25-36 tháng','3-4 tuổi','4-5 tuổi','5-6 tuổi'
    ];

    /* =============== BUILD RANGE NĂM SINH 0..60 TUỔI =============== */
    private function dobYears(string $academicYear): array
    {
        [$yBase] = array_map('intval', explode('-', $academicYear)); // ví dụ 2024-2025 => 2024
        $years = [];
        for ($age = 0; $age <= 60; $age++) $years[] = $yBase - $age;
        return $years; // từ 2024..1964
    }

    /* ======================= MAIN QUERY ======================= */
    private function queryByYears(string $academic, string $scheme, array $geo, array $years): array
    {
        // Lấy PE latest của năm học
        $peLatest = DB::table('person_educations as pe1')
            ->select('pe1.*', DB::raw('ROW_NUMBER() OVER (PARTITION BY pe1.person_id ORDER BY pe1.id DESC) rn'))
            ->where('pe1.academic_year', $academic);

        $qb =DB::table('survey_form_details as d')
            ->join('survey_forms as f', fn($j) => $j->on('f.id','=','d.survey_form_id')->where('f.is_deleted', false))
            ->join('people as p', 'p.id', '=', 'd.person_id') 
            ->leftJoinSub($peLatest,'pe', fn($j)=>$j->on('pe.person_id','=','p.id')->where('pe.rn',1))
            ->leftJoin('education_blocks as eb','eb.id','=','pe.education_block_id')
            ->leftJoin('ethnicities as e','e.id','=','p.ethnicity_id')
            ->where('p.is_deleted',false)
            ->whereIn('p.dob_year',$years);

        // filter địa bàn
        $this->applyGeoFilter($qb, $scheme, $geo);

        // tiện biểu thức
        $isF     = '(p.is_female = 1)';
        $isEth   = '(COALESCE(e.is_minority,0) = 1)';
        $disabled= '(0)'; // nếu bạn có cột người khuyết tật, đổi lại cho đúng

        // lớp hiện tại đang học (1..12). Ta ép tên khối học (eb.name) sang số.
        // Nhiều DB có thể CAST(eb.name AS UNSIGNED) trả về 0 với mầm non ⇒ ok
       
        // mầm non?
        $preschoolList = collect($this->preschoolNames)->map(fn($s)=>DB::getPdo()->quote($s))->implode(',');
         $notpre = "(eb.name NOT IN ($preschoolList))";

$cls     = "(CAST(eb.name AS UNSIGNED))";
$drop    = 'COALESCE(pe.bo_hoc_lop,0)';

        // chưa đi học (never): chưa có block phổ thông và chưa có hoc_xong_lop/bo_hoc_lop
        $never = "( (pe.education_block_id IS NULL OR eb.name IN ($preschoolList))
                    AND COALESCE(pe.hoc_xong_lop,0)=0 AND COALESCE(pe.bo_hoc_lop,0)=0 )";
        $never = "( (pe.education_block_id IS NULL ) 
                    AND COALESCE(pe.hoc_xong_lop,0)=0 AND COALESCE(pe.bo_hoc_lop,0)=0  and COALESCE(pe.graduation_level_id,0)=0)";
        // tốt nghiệp
        //  TH: graduation_level_id = 1
        //  THCS: 2
        //  THPT trở lên hoặc nghề: graduation_level_id >= 3 OR vocational_level_id IS NOT NULL
        $gradTH    = '(COALESCE(pe.graduation_level_id,0)=2)';
        $gradTHCS  = '(COALESCE(pe.graduation_level_id,0)=3)';
        $gradPLUS  = '(COALESCE(pe.graduation_level_id,0)=4 OR COALESCE(pe.vocational_level_id,0)>0)';

        // bỏ học theo lớp 1..12
        $drop = 'COALESCE(pe.bo_hoc_lop,0)';

        // build SELECT động cho 12 lớp (đang học + bỏ học)
        $selStu = $selDrop = [];
        for ($k=1; $k<=12; $k++) {
            $selStu[]  = "SUM(CASE WHEN $notpre AND $cls = $k THEN 1 ELSE 0 END) AS stu_$k";
            $selDrop[] = "SUM(CASE WHEN  $drop = $k THEN 1 ELSE 0 END) AS drop_$k";
        }

        $rows = $qb->selectRaw("
                p.dob_year as y,
                COUNT(*)                                        AS tot,
                SUM(CASE WHEN $isF THEN 1 ELSE 0 END)           AS f,
                SUM(CASE WHEN $isEth THEN 1 ELSE 0 END)         AS eth,
                SUM(CASE WHEN $disabled THEN 1 ELSE 0 END)      AS disabled,
                   -- ===== Đi học =====
                    -- Tổng mầm non
                    SUM(CASE WHEN eb.name IN ($preschoolList) THEN 1 ELSE 0 END) AS stu_pre,
                    -- Tổng đi học (mầm non hoặc 1..12)
                    SUM(CASE WHEN (eb.name IN ($preschoolList)) OR ($cls BETWEEN 1 AND 12) THEN 1 ELSE 0 END) AS stu_tot,

                    ".implode(',', $selStu).",

                -- Tốt nghiệp
                SUM(CASE WHEN $gradTH   THEN 1 ELSE 0 END)      AS grad_th,
                SUM(CASE WHEN $gradTHCS THEN 1 ELSE 0 END)      AS grad_thcs,
                SUM(CASE WHEN $gradPLUS THEN 1 ELSE 0 END)      AS grad_plus,
                -- ===== Chưa đi học & Bỏ học =====
                -- Tổng bỏ học
                SUM(CASE WHEN ($drop BETWEEN 1 AND 12) THEN 1 ELSE 0 END) AS drop_tot,
                -- Không đi học
                SUM(CASE WHEN $never THEN 1 ELSE 0 END) AS never_school,

                ".implode(',', $selDrop).",
                -- Bỏ học nhưng đã tốt nghiệp TH, THCS
                SUM(CASE WHEN ($drop BETWEEN 1 AND 12) AND $gradTH   THEN 1 ELSE 0 END) AS drop_grad_th,
                SUM(CASE WHEN ($drop BETWEEN 1 AND 12) AND $gradTHCS THEN 1 ELSE 0 END) AS drop_grad_thcs
            ")
            ->groupBy('p.dob_year')
            ->orderBy('p.dob_year','desc')
            ->get()
            ->keyBy('y');

        // chuẩn hoá mảng trả về (đảm bảo đủ tất cả năm)
        $out = [];
        foreach ($years as $y) {
            $r = $rows[$y] ?? null;
            $row = [
                'tot'=>0,'f'=>0,'eth'=>0,'disabled'=>0,
                'stu_pre'=>0,'stu_tot'=>0,   
                'grad_th'=>0,'grad_thcs'=>0,'grad_plus'=>0,'never_school'=>0,
                 'drop_tot'=>0,'never_school'=>0,   
                'drop_grad_th'=>0,'drop_grad_thcs'=>0,
            ];
            for ($k=1;$k<=12;$k++) { $row["stu_$k"]=0; $row["drop_$k"]=0; }
            if ($r) {
                foreach ($row as $k=>$_) { $row[$k] = (int)($r->$k ?? 0); }
                for ($k=1;$k<=12;$k++) {
                    $row["stu_$k"]  = (int)($r->{"stu_$k"}  ?? 0);
                    $row["drop_$k"] = (int)($r->{"drop_$k"} ?? 0);
                }
            }
            $row['stu_pre']      = (int)($r->stu_pre ?? 0);
            $row['stu_tot']      = (int)($r->stu_tot ?? 0);
            $row['drop_tot']     = (int)($r->drop_tot ?? 0) + (int)($r->never_school ?? 0);
            $row['never_school'] = (int)($r->never_school ?? 0);
            $out[$y] = $row;
        }
        return $out;
    }

    /* ======================= EXPORT ======================= */
    public function export(Request $req)
    {
        $academic = (string)$req->input('year', \App\Models\SurveyForm::currentAcademicYear());
        [$yBase]  = array_map('intval', explode('-', $academic));
        $scheme   = (string)$req->input('scheme','old');
        $geo      = (array)$req->input('geo', []);

        $years = $this->dobYears($academic); // 0..60 tuổi
        $data  = $this->queryByYears($academic, $scheme, $geo, $years);

        $cfg = $this->templateCfg();
        $wb  = IOFactory::load($cfg['path']);
        $sh  = $wb->getActiveSheet();

        // Header
        $sh->setCellValue($cfg['header']['province'], $this->geoService->geoNameFromId($geo['province_id'] ?? null));
        $sh->setCellValue($cfg['header']['ward'],     $this->geoService->geoNameFromId($geo['ward_id'] ?? null));
          $sh->setCellValue('C3',     $this->geoService->geoNameFromId($geo['village_id'] ?? null));
        $sh->setCellValue($cfg['header']['year'],     $academic);
        $sh->setCellValue('G3', $this->formatService->top_date());

        // chuẩn bị dòng
        $start = $cfg['startRow'];
        $need  = count($years)-1;
        if ($need>0) {
            $sh->insertNewRowBefore($start+1, $need);
            for ($i=1; $i<=$need; $i++) {
                $this->replicateRowFormatMerged($sh, $start, $start+$i, $cfg['lastCol']);
            }
        }

        // helper fill 1 row
        $c = $cfg['col']; $r = $start; $rowsForTotals = [];
        foreach ($years as $y) {
            $age = $yBase - $y;
            $rowsForTotals[] = $r;

            $sh->setCellValue($c['dob'].$r, $y);
            $sh->setCellValue($c['age'].$r, $age);

            $d = $data[$y];

            // tổng – nữ – dân tộc – khuyết tật
            $sh->setCellValue($c['tot'].$r, $d['tot']);
            $sh->setCellValue($c['f'].$r,   $d['f']);
            $sh->setCellValue($c['eth'].$r, $d['eth']);
            $sh->setCellValue($c['disabled'].$r, $d['disabled']);
            # ===== Đi học =====
            $sh->setCellValue($c['stu_tot'].$r, $d['stu_tot'] ?: null); // Tổng đi học
            $sh->setCellValue($c['stu_pre'].$r, $d['stu_pre'] ?: null); // Tổng mầm non
                        // đang học 1..12
            for ($k=1;$k<=12;$k++) {
                $col = $c["stu_$k"];
                $sh->setCellValue($col.$r, $d["stu_$k"] ?: null);
            }

            // tốt nghiệp
            $sh->setCellValue($c['grad_th'].$r,   $d['grad_th'] ?: null);
            $sh->setCellValue($c['grad_thcs'].$r, $d['grad_thcs'] ?: null);
            $sh->setCellValue($c['grad_plus'].$r, $d['grad_plus'] ?: null);
            // // $sh->setCellValue($c['grad_tot'].$r,
            //     sprintf('=%s%d+%s%d+%s%d', $c['grad_th'],$r, $c['grad_thcs'],$r, $c['grad_plus'],$r));

            # ===== Bỏ học =====
            $sh->setCellValue($c['drop_tot'].$r,  $d['drop_tot'] ?: null);     // Tổng bỏ học
            $sh->setCellValue($c['never'].$r,     $d['never_school'] ?: null); // Không đi học
            
            // bỏ học theo lớp
            for ($k=1;$k<=12;$k++) {
                $col = $c["drop_$k"];
                $sh->setCellValue($col.$r, $d["drop_$k"] ?: null);
            }
            // tổng phụ bỏ học đã TN
            $sh->setCellValue($c['drop_grad_th'].$r,   $d['drop_grad_th'] ?: null);
            $sh->setCellValue($c['drop_grad_thcs'].$r, $d['drop_grad_thcs'] ?: null);

            $r++;
        }

        /* ===== Chèn các dòng tổng mốc tuổi ===== */
        $insertSubtotal = function(int $fromR, int $toR, string $label) use ($sh,$c,$cfg) {
            $row = $toR + 1;
            $sh->insertNewRowBefore($row, 1);
            $this->replicateRowFormatMerged($sh, $toR, $row, $cfg['lastCol']);

            // Gộp 2 ô A:B và ghi nhãn
            $sh->mergeCells($c['dob'].$row.':'.$c['age'].$row);
            $sh->setCellValue($c['dob'].$row, "TC: $label");

            // Danh sách cột cần SUM (bổ sung stu_tot, stu_pre, never)
            $cols = [
                'tot','f','eth','disabled',
                'stu_tot','stu_pre',          // <- mới
                'grad_th','grad_thcs','grad_plus', 
                'never',                      // đã có, giữ nguyên
                'drop_tot',                   // nếu bạn muốn tổng bỏ học, có thể giữ hoặc xoá dòng này
                'drop_grad_th','drop_grad_thcs',
            ];
            for ($k=1; $k<=12; $k++) { $cols[] = "stu_$k"; $cols[] = "drop_$k"; }

            foreach ($cols as $key) {
                $col = $c[$key];
                $sh->setCellValue($col.$row, "=SUM($col$fromR:$col$toR)");
            }

            // Style: in đậm + nền xám nhạt, căn giữa theo chiều dọc
            $range = $c['dob'].$row.':'.$cfg['lastCol'].$row;
            $sh->getStyle($range)->getFont()->setBold(true);
            $sh->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
            $sh->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            return $row; // <— trả về dòng subtotal
        };

        // tính theo “age” đang viết ở cột age
        $findRowByAge = function(int $age) use ($start,$years,$yBase) {
            // dòng của tuổi N = vị trí của $yBase-$age trong mảng $years
            $y = $yBase - $age;
            $idx = array_search($y, $years, true); // 0-based
            return $start + $idx;
        };
        $subRows = [];
        // 0–2
        $subRows[] =$insertSubtotal($findRowByAge(0),  $findRowByAge(2),  '0-2');
        // 3–5
        $subRows[] = $insertSubtotal($findRowByAge(3)+1, $findRowByAge(5)+1, '3-5');
        // 6–14
         $subRows[] =$insertSubtotal($findRowByAge(6)+2, $findRowByAge(14)+2, '6-14');
        // 15–18
        $subRows[] = $insertSubtotal($findRowByAge(15)+3, $findRowByAge(18)+3, '15-18');
        // 19–25
        $subRows[] = $insertSubtotal($findRowByAge(19)+4, $findRowByAge(25)+4, '19-25');
        // 26–35
         $subRows[] =$insertSubtotal($findRowByAge(26)+5, $findRowByAge(35)+5, '26-35');
        // 36–60
        $subRows[] = $insertSubtotal($findRowByAge(36)+6, $findRowByAge(60)+6, '36-60');

        // Tổng 0–60 (cuối bảng)
        // $lastDataRow = $findRowByAge(60)+7;
        // $sumRow = $lastDataRow + 1;
        // $sh->insertNewRowBefore($lastDataRow+1,1);
        // $this->replicateRowFormatMerged($sh, $lastDataRow, $lastDataRow+1, $cfg['lastCol']);
        // $sh->setCellValue($c['dob'].($lastDataRow+1), 'TC: 0-60');
        // $cols = ['tot','f','eth','disabled','never','grad_th','grad_thcs','grad_plus','grad_tot','drop_grad_th','drop_grad_thcs'];
        // for ($k=1;$k<=12;$k++) { $cols[]="stu_$k"; $cols[]="drop_$k"; }
        // foreach ($cols as $key) {
        //     $col = $c[$key];
        //     $sh->setCellValue($col.($lastDataRow+1), "=SUM($col$start:$col$lastDataRow)");
        // }

        $lastDataRow = $findRowByAge(60) + 7;

        // chèn 1 dòng mới sau lastDataRow
        $sumRow = $lastDataRow + 1;
        $sh->insertNewRowBefore($sumRow, 1);
        $this->replicateRowFormatMerged($sh, $lastDataRow, $sumRow, $cfg['lastCol']);

        // gộp A:B và đặt nhãn
        $sh->mergeCells($c['dob'].$sumRow.':'.$c['age'].$sumRow);
        $sh->setCellValue($c['dob'].$sumRow, 'TC: 0-60');

        // các cột cần SUM (bổ sung stu_tot, stu_pre, never)
        $cols = [
            'tot','f','eth','disabled',
            'stu_tot','stu_pre',          // <- mới
            'grad_th','grad_thcs','grad_plus' ,
            'never',                      // <- mới
            'drop_tot',                   // nếu cần tổng bỏ học
            'drop_grad_th','drop_grad_thcs',
        ];
        for ($k=1; $k<=12; $k++) { $cols[] = "stu_$k"; $cols[] = "drop_$k"; }

        // Công thức dạng =SUM(G12,G25,G40,...) theo các dòng subtotal
        foreach ($cols as $key) {
            $col = $c[$key];
            $refs = implode(',', array_map(fn($r)=>$col.$r, $subRows));
            $sh->setCellValue($col. $sumRow, "=SUM($refs)");
        }

        // Style tổng 0–60
        $range = $c['dob'].$sumRow.':'.$cfg['lastCol'].$sumRow;
        $sh->getStyle($range)->getFont()->setBold(true);
        $sh->getStyle($range)->getFill()
        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
        $sh->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        // Xuất
        // --- ẨN SỐ 0 TOÀN BẢNG ---
        $firstDataRow = $cfg['startRow'];
        $finalRow     = $sumRow; // $sumRow chính là dòng "TC: 0-60" bạn vừa tạo

        // Áp dụng format ẩn số 0 cho toàn bộ vùng số liệu (từ cột C tới cột cuối)
        $sh->getStyle(sprintf('C%d:%s%d', $firstDataRow, $cfg['lastCol'], $finalRow))
        ->getNumberFormat()->setFormatCode('#,##0;-#,##0;;@');
        $this->setCellValue($sh, 'AD79' , $this->formatService->end_date($this->geoService->geoNameFromId($geo['ward_id'] ?? null)));
        $sh->mergeCells('AD79:AL79' );
        $sh->getStyle('AD79:AL79' )->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return response()->streamDownload(function () use ($wb) {
            $writer = IOFactory::createWriter($wb, 'Xlsx');
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $this->getExportFilename($cfg['path']), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
