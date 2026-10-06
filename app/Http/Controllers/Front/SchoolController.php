<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolType;
use App\Models\EducationalLevel;
use App\Services\GeoService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
 
use App\Models\GeoUnit;

 
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SchoolController extends Controller
{
    public function index(Request $req)
    {
        $types  = SchoolType::orderBy('name')->get(['id','name']);
        $levels = EducationalLevel::orderBy('name')->get(['id','name']);
        return view('front.schools.index', compact('types','levels'));
    }

    /** Build query chung cho JSON & Export */
    protected function baseQuery(Request $req, GeoService $geo)
    {
        $q = School::query()
            ->with([
                'schoolType:id,name',
                'educationalLevel:id,name',
                'wardUnitNew:id,code,name,parent_id,scheme',     // cấp 3 (mới)
                'wardUnitNew.parent:id,code,name,scheme',        // tỉnh (mới) — vì Lv3 mới có parent là Lv1
            ]);

        // Tìm kiếm tự do theo mã / tên
        if ($req->filled('q')) {
            $term = trim($req->q);
            $q->where(function($w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                  ->orWhere('code', 'like', "%{$term}%");
            });
        }

        // Lọc theo loại hình / bậc học
        if ($req->filled('school_type_id')) {
            $q->where('school_type_id', $req->school_type_id);
        }
        if ($req->filled('educational_level_id')) {
            $q->where('educational_level_id', $req->educational_level_id);
        }

        // Lọc tình trạng giải thể: exclude | only | all
        $d = $req->input('dissolved', 'exclude');
        if ($d === 'only') {
            $q->where('is_dissolved', true);
        } elseif ($d === 'exclude') {
            $q->where(function($w){
                $w->whereNull('is_dissolved')->orWhere('is_dissolved', false);
            });
        }

        // Lọc theo địa chỉ mới (ưu tiên ward nếu có)
        if ($req->filled('ward_code_new')) {
            $q->where('ward_code_new', $req->ward_code_new);
        } elseif ($req->filled('province_code_new')) {
            $wardCodes = $geo->newWardCodesByProvince($req->province_code_new);
            if (!empty($wardCodes)) {
                $q->whereIn('ward_code_new', $wardCodes);
            } else {
                // Không có xã nào dưới tỉnh → trả rỗng
                $q->whereRaw('1=0');
            }
        }

        return $q;
    }
 
public function picker(Request $r)
{
    // Base query (nhẹ, chỉ chọn các cột cần)
    $q = School::query()
        ->select([
            'id','code','name',
            'province_code_new','ward_code_new',
            'province_code','district_code','ward_code',
        ]);

    // Eager-load quan hệ nếu cần ghi nhãn ở client
    if ($r->boolean('with_relations')) {
        $q->with([
            'provinceNew:id,code,name',
            'wardNew:id,code,name',
            'provinceOld:id,code,name',
            'districtOld:id,code,name',
            'wardOld:id,code,name',
        ]);
    }

    // --- search term ---
    if ($term = trim((string) $r->input('q'))) {
        $q->where(function ($qq) use ($term) {
            $qq->where('code','like',"%{$term}%")
               ->orWhere('name','like',"%{$term}%");
        });
    }

    // Helper: đổi "id hoặc code" -> code theo scheme & level
    $asCode = function ($val, string $scheme, int $level) {
        if ($val === null || $val === '') return null;

        // 1) Nếu đã là code hợp lệ
        $byCode = GeoUnit::where('scheme',$scheme)->where('type_level',$level)
            ->where('code',$val)->first();
        if ($byCode) return $byCode->code;

        // 2) Nếu là số, có thể là id
        if (preg_match('/^\d+$/', (string)$val)) {
            $byId = GeoUnit::find((int)$val);
            if ($byId && $byId->scheme === $scheme && (int)$byId->type_level === $level) {
                return $byId->code;
            }
        }

        // 3) Scheme old: thử prefix 'cu_'
        if ($scheme === 'old' && !str_starts_with((string)$val, 'cu_')) {
            $pref = 'cu_'.$val;
            $byPref = GeoUnit::where('scheme','old')->where('type_level',$level)
                ->where('code',$pref)->first();
            if ($byPref) return $byPref->code;
        }

        // Fallback
        return $val;
    };

    /* --------- LỌC THAM SỐ TRỰC TIẾP --------- */
    // NEW
    if ($r->filled('province_code_new')) {
        $q->where('province_code_new', $asCode($r->input('province_code_new'), 'new', 1));
    }
    if ($r->filled('ward_code_new')) {
        $q->where('ward_code_new', $asCode($r->input('ward_code_new'), 'new', 3));
    }

    // OLD (chấp nhận alias không _old)
    $provOld = $r->input('province_code_old', $r->input('province_code'));
    $distOld = $r->input('district_code_old', $r->input('district_code'));
    $wardOld = $r->input('ward_code_old',     $r->input('ward_code'));

    if ($provOld !== null && $provOld !== '') {
        $q->where('province_code', $asCode($provOld, 'old', 1));
    }
    if ($distOld !== null && $distOld !== '') {
        $q->where('district_code', $asCode($distOld, 'old', 2));
    }
    if ($wardOld !== null && $wardOld !== '') {
        $q->where('ward_code', $asCode($wardOld, 'old', 3));
    }

    /* --------- LỌC THEO geo[...] + scheme --------- */
    if (is_array($geo = $r->input('geo')) && !empty($geo)) {
        $scheme = $r->input('scheme', 'new');
        if ($scheme === 'new') {
            if (!empty($geo['province_id'])) {
                $q->where('province_code_new', $asCode($geo['province_id'], 'new', 1));
            }
            if (!empty($geo['ward_id'])) {
                $q->where('ward_code_new', $asCode($geo['ward_id'], 'new', 3));
            }
        } else {
            if (!empty($geo['province_id'])) {
                $q->where('province_code', $asCode($geo['province_id'], 'old', 1));
            }
            if (!empty($geo['district_id'])) {
                $q->where('district_code', $asCode($geo['district_id'], 'old', 2));
            }
            if (!empty($geo['ward_id'])) {
                $q->where('ward_code', $asCode($geo['ward_id'], 'old', 3));
            }
        }
    }

    // Giới hạn an toàn
    $limit = (int) $r->input('limit', 500);
    $limit = max(1, min($limit, 2000));

    // Sắp xếp nhẹ nhàng: ưu tiên theo tên, rồi theo mã
    $items = $q->orderBy('name')->orderBy('code')->limit($limit)->get();

    // Có thể ánh xạ ra output gọn cho picker (tuỳ front-end)
    if ($r->boolean('compact')) {
        $idKey   = $r->input('id_key', 'id');
        $nameKey = $r->input('name_key', 'name');
        $codeKey = $r->input('code_key', 'code');

        $out = $items->map(function ($s) use ($idKey, $nameKey, $codeKey) {
            return [
                $idKey   => $s->id,
                $nameKey => $s->name,
                $codeKey => $s->code,
            ];
        });

        return response()->json($out->values());
    }

    // Trả về đầy đủ (nhưng không phân trang)
    return response()->json($items->values());
}

    public function datapicker(Request $r)
    {   
        $q = School::query()
            ->with([
                // quan hệ phải có trong App\Models\School như đã hướng dẫn
                'provinceNew:id,code,name',
                'wardNew:id,code,name',
                'provinceOld:id,code,name',
                'districtOld:id,code,name',
                'wardOld:id,code,name',
            ])
            ->select([
                'id','code','name',
                'province_code_new','ward_code_new',
                'province_code','district_code','ward_code',
            ]);

        // --- search term ---
        if ($term = trim((string) $r->input('q'))) {
            $q->where(function ($qq) use ($term) {
                $qq->where('code','like',"%{$term}%")
                ->orWhere('name','like',"%{$term}%");
            });
        }

        // Helper: đổi "id hoặc code" -> code theo scheme & level
        $asCode = function ($val, string $scheme, int $level) {
            if ($val === null || $val === '') return null;

            // 1) Thử coi đây đã là code hợp lệ chưa
            $byCode = GeoUnit::where('scheme',$scheme)->where('type_level',$level)
                ->where('code',$val)->first();
            if ($byCode) return $byCode->code;

            // 2) Nếu là số -> có thể là ID
            if (preg_match('/^\d+$/', (string)$val)) {
                $byId = GeoUnit::find((int)$val);
                if ($byId && $byId->scheme === $scheme && (int)$byId->type_level === $level) {
                    return $byId->code;
                }
            }

            // 3) Với scheme=old, thử auto prefix 'cu_'
            if ($scheme === 'old' && !str_starts_with((string)$val, 'cu_')) {
                $pref = 'cu_'.$val;
                $byPref = GeoUnit::where('scheme','old')->where('type_level',$level)
                    ->where('code',$pref)->first();
                if ($byPref) return $byPref->code;
            }

            // Trả nguyên (đề phòng DB đang lưu đúng giá trị đó)
            return $val;
        };

        // --------- LỌC THEO THAM SỐ TRỰC TIẾP ----------
        // NEW
        if ($r->filled('province_code_new')) {
            $q->where('province_code_new', $asCode($r->input('province_code_new'), 'new', 1));
        }
        if ($r->filled('ward_code_new')) {
            $q->where('ward_code_new', $asCode($r->input('ward_code_new'), 'new', 3));
        }

        // OLD (chấp nhận cả tên có/không _old)
        $provOld = $r->input('province_code_old', $r->input('province_code'));
        $distOld = $r->input('district_code_old', $r->input('district_code'));
        $wardOld = $r->input('ward_code_old',     $r->input('ward_code'));

        if ($provOld !== null && $provOld !== '') {
            $q->where('province_code', $asCode($provOld, 'old', 1));
        }
        if ($distOld !== null && $distOld !== '') {
            $q->where('district_code', $asCode($distOld, 'old', 2));
        }
        if ($wardOld !== null && $wardOld !== '') {
            $q->where('ward_code', $asCode($wardOld, 'old', 3));
        }

        // --------- LỌC THEO geo[...] + scheme ----------
        if (is_array($geo = $r->input('geo')) && !empty($geo)) {
            $scheme = $r->input('scheme', 'new');
            if ($scheme === 'new') {
                if (!empty($geo['province_id'])) {
                    $q->where('province_code_new', $asCode($geo['province_id'], 'new', 1));
                }
                if (!empty($geo['ward_id'])) {
                    $q->where('ward_code_new', $asCode($geo['ward_id'], 'new', 3));
                }
            } else {
                if (!empty($geo['province_id'])) {
                    $q->where('province_code', $asCode($geo['province_id'], 'old', 1));
                }
                if (!empty($geo['district_id'])) {
                    $q->where('district_code', $asCode($geo['district_id'], 'old', 2));
                }
                if (!empty($geo['ward_id'])) {
                    $q->where('ward_code', $asCode($geo['ward_id'], 'old', 3));
                }
            }
        }

        return $q->paginate($r->integer('per_page', 10));
    }
    /** API JSON cho bảng frontend */
    public function data(Request $req, GeoService $geo)
    {
        $per = (int) $req->input('per_page', 20);
        if ($per < 1 || $per > 200) $per = 50;

        $p = $this->baseQuery($req, $geo)->paginate($per);

        return response()->json([
            'current_page' => $p->currentPage(),
            'last_page'    => $p->lastPage(),
            'per_page'     => $p->perPage(),
            'total'        => $p->total(),
            'data'         => $p->getCollection()->map(function (School $s) {
                return [
                    'code'               => $s->code,
                    'name'               => $s->name,
                    'school_type'        => $s->schoolType?->name,
                    'educational_level'  => $s->educationalLevel?->name,
                    'ward_code_new'      => $s->ward_code_new,
                    'ward_new'           => $s->wardUnitNew
                        ? ['code'=>$s->wardUnitNew->code, 'name'=>$s->wardUnitNew->name]
                        : null,
                    'province_new'       => $s->wardUnitNew?->parent
                        ? ['code'=>$s->wardUnitNew->parent->code, 'name'=>$s->wardUnitNew->parent->name]
                        : null,
                    'is_dissolved'       => (bool) $s->is_dissolved,
                ];
            }),
        ]);
    }

    /** Xuất Excel theo các filter hiện tại */
    public function export(Request $req, \App\Services\GeoService $geo)
{
    // == Dùng cùng logic lọc như index ==
    $matchNewWards = $matchOldWards = $matchOldDistricts = [];

    // --- bộ lọc CŨ ---
    if ($req->filled('ward_code')) {
        $matchOldWards[] = $req->ward_code;
    } elseif ($req->filled('district_code')) {
        $matchOldDistricts[] = $req->district_code;
        $matchOldWards = array_merge($matchOldWards, $geo->oldWardCodesByDistrict($req->district_code));
    } elseif ($req->filled('province_code')) {
        [$dCodes, $wCodes] = $geo->oldCodesByProvince($req->province_code);
        $matchOldDistricts = array_merge($matchOldDistricts, $dCodes);
        $matchOldWards     = array_merge($matchOldWards, $wCodes);
    }

    // --- bộ lọc MỚI ---
    if ($req->filled('ward_code_new')) {
        $matchNewWards[] = $req->ward_code_new;
        $mapped = $geo->oldCodesMappedFromNewWard($req->ward_code_new);
        $matchOldWards     = array_merge($matchOldWards, $mapped['l3'] ?? []);
        $matchOldDistricts = array_merge($matchOldDistricts, $mapped['l2'] ?? []);
    } elseif ($req->filled('province_code_new')) {
        $matchNewWards = array_merge($matchNewWards, $geo->newWardCodesByProvince($req->province_code_new));
        $mapped = $geo->oldCodesMappedFromNewProvince($req->province_code_new);
        $matchOldWards     = array_merge($matchOldWards, $mapped['old_ward_codes'] ?? []);
        $matchOldDistricts = array_merge($matchOldDistricts, $mapped['old_district_codes'] ?? []);
    }

    // unique + loại rỗng
    $matchNewWards     = array_values(array_unique(array_filter($matchNewWards)));
    $matchOldWards     = array_values(array_unique(array_filter($matchOldWards)));
    $matchOldDistricts = array_values(array_unique(array_filter($matchOldDistricts)));

    // Lọc tình trạng giải thể: exclude | only | all
    $dissolved = $req->input('dissolved', 'exclude');

    // Query
    $rows = \App\Models\School::with(['schoolType','educationalLevel','wardUnitNew','wardUnitNew.parent'])
        ->when($req->filled('q'), fn($x)=>$x->where(fn($w)=>$w
            ->where('name','like','%'.$req->q.'%')
            ->orWhere('code','like','%'.$req->q.'%')
        ))
        ->when($req->filled('school_type_id'), fn($x)=>$x->where('school_type_id',$req->school_type_id))
        ->when($req->filled('educational_level_id'), fn($x)=>$x->where('educational_level_id',$req->educational_level_id))
        ->when($dissolved === 'only', fn($x)=>$x->where('is_dissolved', true))
        ->when($dissolved === 'exclude', fn($x)=>$x->where(fn($w)=>$w->whereNull('is_dissolved')->orWhere('is_dissolved', false)))
        ->when(($matchNewWards || $matchOldWards || $matchOldDistricts), function($x) use ($matchNewWards,$matchOldWards,$matchOldDistricts){
            $x->where(function($w) use ($matchNewWards,$matchOldWards,$matchOldDistricts){
                if ($matchNewWards)     $w->orWhereIn('ward_code_new', $matchNewWards);
                if ($matchOldWards)     $w->orWhereIn('ward_code',     $matchOldWards);
                if ($matchOldDistricts) $w->orWhereIn('district_code', $matchOldDistricts);
            });
        })
        ->orderBy('name')
        ->get();

    // == Build Excel ==
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Danh mục trường');

    // Title
    $sheet->mergeCells('A1:H1');
    $sheet->setCellValue('A1', 'DANH MỤC TRƯỜNG');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(1)->setRowHeight(26);

    // Header
    $headers = [
        'A3' => 'STT',
        'B3' => 'Mã trường',
        'C3' => 'Tên trường',
        'D3' => 'Loại hình trường',
        'E3' => 'Bậc học',
        'F3' => 'Phường/Xã (mới)',
        'G3' => 'Tỉnh/Thành (mới)',
        'H3' => 'Mã phường/xã (mới)',
    ];
    foreach ($headers as $cell => $text) { $sheet->setCellValue($cell, $text); }
    $sheet->getStyle('A3:H3')->applyFromArray([
        'font' => ['bold' => true],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_CENTER,
            'wrapText'   => true,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'color'    => ['rgb' => 'F2F2F2']
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color'=>['argb'=>'FF999999']]
        ],
    ]);
    $sheet->getRowDimension(3)->setRowHeight(22);

    // Column widths
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(22);
    $sheet->getColumnDimension('C')->setWidth(42);
    $sheet->getColumnDimension('D')->setWidth(22);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(26);
    $sheet->getColumnDimension('G')->setWidth(28);
    $sheet->getColumnDimension('H')->setWidth(22);

    // Data
    $r = 4; $stt = 1;
    foreach ($rows as $s) {
        $sheet->setCellValueExplicit("A{$r}", $stt++, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValue("B{$r}", $s->code);
        $sheet->setCellValue("C{$r}", $s->name);
        $sheet->setCellValue("D{$r}", optional($s->schoolType)->name);
        $sheet->setCellValue("E{$r}", optional($s->educationalLevel)->name);
        $sheet->setCellValue("F{$r}", optional($s->wardUnitNew)->name);           // phường/xã (mới)
        $sheet->setCellValue("G{$r}", optional($s->wardUnitNew?->parent)->name);  // tỉnh/thành (mới)
        $sheet->setCellValue("H{$r}", $s->ward_code_new);
        $r++;
    }
    $last = $r - 1;

    // Style vùng dữ liệu
    if ($last >= 4) {
        // viền
        $sheet->getStyle("A4:H{$last}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle'=>Border::BORDER_THIN, 'color'=>['argb'=>'FFCCCCCC']]
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP]
        ]);
        // wrap text cho tên
        $sheet->getStyle("C4:G{$last}")->getAlignment()->setWrapText(true);
        // căn giữa STT & các mã
        $sheet->getStyle("A4:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("H4:H{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // Freeze header + AutoFilter
    $sheet->freezePane('A4');
    $sheet->setAutoFilter("A3:H3");

    // Tên file
    $filename = 'danh_muc_truong_'.now()->format('Ymd_His').'.xlsx';

    // Xuất
    return response()->streamDownload(function() use ($spreadsheet) {
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }, $filename, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}

    /**
     * Cập nhật loại hình trường (công lập/ngoài công lập)
     */
    public function updateConglap($schoolId, Request $request)
    {
        try {
            $user = auth()->user();
            
            // Kiểm tra user có phải cấp trường không
            if (!$user->isSchool()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chỉ tài khoản cấp trường mới có thể thực hiện thao tác này'
                ], 403);
            }
            
            // Kiểm tra user có quyền truy cập trường này không
            if ($user->school_id != $schoolId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn không có quyền truy cập trường này'
                ], 403);
            }
            
            $request->validate([
                'is_conglap' => 'required|boolean'
            ]);
            
            $school = School::findOrFail($schoolId);
            $school->update([
                'is_conglap' => $request->is_conglap
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật loại hình trường thành công',
                'data' => [
                    'is_conglap' => $school->is_conglap
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Update school conglap error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật'
            ], 500);
        }
    }
}
