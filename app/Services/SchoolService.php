<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Models\GeoUnit;
use App\Services\GeoService;
use App\Services\PermissionService;
use App\Models\EducationalLevel;
use App\Models\SchoolType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Access\AuthorizationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use Illuminate\Validation\ValidationException;
class SchoolService
{
    public function __construct(
        protected PermissionService $permissionService,
        protected GeoService $geo
    ) {}

  protected function likePrefix(string $code): string
    {
        // escape backslash, underscore và percent
        $e = str_replace(['\\','_','%'], ['\\\\','\_','\%'], $code);
        return $e.'\_%'; // ví dụ 'cu\_66\_%'
    }

    /** Dựng query lọc giống trang index */
    public function buildFilteredQuery(array $filters = [])
    {
        $q = School::query()->with(['schoolType','educationalLevel'])->orderBy('name');

        // Áp dụng phân quyền theo user hiện tại
        $currentUser = \Illuminate\Support\Facades\Auth::user();
        if ($currentUser instanceof User && !$currentUser->isAdmin()) {
            $manageableSchoolIds = $currentUser->getAccessibleSchoolIds();
            if (!empty($manageableSchoolIds)) {
                $q->whereIn('id', $manageableSchoolIds);
            } else {
                // Nếu không có quyền truy cập trường nào, trả về kết quả rỗng
                $q->whereRaw('1 = 0');
            }
        }

        // Từ khoá
        if (!empty($filters['q'])) {
            $kw = trim((string)$filters['q']);
            $q->where(function($x) use ($kw){
                $x->where('name','like',"%$kw%")
                  ->orWhere('code','like',"%$kw%");
            });
        }

        // Loại hình / bậc học (tên tham số theo form index)
        if (!empty($filters['school_type_id'])) {
            $q->where('school_type_id', (int)$filters['school_type_id']);
        }
        if (!empty($filters['educational_level_id'])) {
            $q->where('educational_level_id', (int)$filters['educational_level_id']);
        }

        // Địa phận cũ — ưu tiên sâu nhất: ward_code > district_code > province_code
        if (!empty($filters['ward_code'])) {
            $ward = (string)$filters['ward_code'];
            $q->where('ward_code', $ward);
        } elseif (!empty($filters['district_code'])) {
            $dist = (string)$filters['district_code'];
            $q->where(function($x) use ($dist){
                $x->where('district_code', $dist)                      // trường lưu theo quận/huyện
                  ->orWhere('ward_code', 'like', $this->likePrefix($dist)); // hoặc phường thuộc quận
            });
        } elseif (!empty($filters['province_code'])) {
            $prov = (string)$filters['province_code'];
            $prefix = $this->likePrefix($prov);
            $q->where(function($x) use ($prefix){
                $x->where('district_code', 'like', $prefix) // các quận thuộc tỉnh
                  ->orWhere('ward_code', 'like', $prefix);  // hoặc phường thuộc tỉnh
            });
        }

        return $q;
    }

    /** Export theo KẾT QUẢ LỌC (toàn bộ tập lọc, không chỉ 1 trang) */
    public function exportXlsx(array $filters = [])
    {
        $this->permissionService->requireAdmin(\Illuminate\Support\Facades\Auth::user());

        // 1) Lấy đúng dữ liệu sau lọc
        $schools = $this->buildFilteredQuery($filters)->get();

        // 2) Map địa danh (để hiện tên theo code)
        $wardMap = GeoUnit::where('scheme','old')->where('type_level',3)->pluck('name','code')->all();
        $distMap = GeoUnit::where('scheme','old')->where('type_level',2)->pluck('name','code')->all();

        // 3) Xuất Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Danh sách trường');

        $headers = ['#','MÃ','TÊN TRƯỜNG','LOẠI HÌNH','BẬC HỌC','PHƯỜNG/XÃ','MÃ PX','QUẬN/HUYỆN','MÃ QH'];
        foreach ($headers as $i => $h) { $sheet->setCellValueByColumnAndRow($i+1, 1, $h); }

        $r = 2; $i = 1;
        foreach ($schools as $s) {
            $sheet->setCellValueByColumnAndRow(1, $r, $i++);
            $sheet->setCellValueByColumnAndRow(2, $r, $s->code);
            $sheet->setCellValueByColumnAndRow(3, $r, $s->name);
            $sheet->setCellValueByColumnAndRow(4, $r, optional($s->schoolType)->name);
            $sheet->setCellValueByColumnAndRow(5, $r, optional($s->educationalLevel)->name);
            $sheet->setCellValueByColumnAndRow(6, $r, $s->ward_code ? ($wardMap[$s->ward_code] ?? null) : null);
            $sheet->setCellValueByColumnAndRow(7, $r, $s->ward_code);
            $sheet->setCellValueByColumnAndRow(8, $r, $s->district_code ? ($distMap[$s->district_code] ?? null) : null);
            $sheet->setCellValueByColumnAndRow(9, $r, $s->district_code);
            $r++;
        }

        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A','I') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        $filename = 'schools_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
    

public function import(string $uploadedPath, \App\Services\GeoService $geo): array
{
    $this->permissionService->requireAdmin(\Illuminate\Support\Facades\Auth::user());

    // Tránh hết thời gian với file lớn
    @ini_set('memory_limit', '-1');
    @set_time_limit(0);
    \Illuminate\Support\Facades\DB::disableQueryLog();

    // Đọc Excel
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($uploadedPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows  = $sheet->toArray(null, true, true, true); // keys: A,B,C,...

    if (count($rows) < 2) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'file' => 'Tệp không có dữ liệu.'
        ]);
    }

    // Dò hàng tiêu đề trong 15 dòng đầu
    [$headers, $dataRows, $headerRowIndex] = $this->extractHeader($rows, 15);
    $idx = fn(string $name) => $headers[$name] ?? null;

    // Các cột chuẩn (nhiều bí danh đã được map trong extractHeader)
    $colCode          = $idx('ma_truong');
    $colName          = $idx('ten_truong');
    $colType          = $idx('loai_hinh_truong');   // có thể null
    $colLevel         = $idx('bac_hoc');            // có thể null
    $colWardName      = $idx('ten_phuong_xa');      // fallback theo tên (tùy chọn)
    $colDistrictName  = $idx('ten_quan_huyen');     // fallback theo tên (tùy chọn)

    // Mã địa giới
    $colWardCodeOld   = $idx('ma_phuong_xa');       // CŨ (có thể dạng "66_643_24145")
    $colDistCodeOld   = $idx('ma_quan_huyen');      // CŨ (có thể dạng "66_643")
    $colWardCodeNew   = $idx('ma_phuong_xa_moi');   // MỚI (chuẩn: "24448"...)

    if (is_null($colCode) || is_null($colName)) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'file' => 'Không tìm thấy hàng tiêu đề phù hợp: cần có "Mã trường" và "Tên trường".'
        ]);
    }

    // === Helpers cho việc chuẩn hoá mã ===
    $extractTriplet = function (?string $s): ?array {
        if (!$s) return null;
        $s = trim($s);
        // Bắt 3 nhóm số bất kỳ: "66_643_24145", "cu-66-643-24145", "66 643 24145", ...
        if (preg_match('/(\d+)\D+(\d+)\D+(\d+)/', $s, $m)) {
            // giữ nguyên chuỗi số (không ép int) để bảo toàn số 0 ở đầu
            return [$m[1], $m[2], $m[3]]; // [prov, dist, ward]
        }
        return null;
    };

    $extractPair = function (?string $s): ?array {
        if (!$s) return null;
        $s = trim($s);
        if (preg_match('/(\d+)\D+(\d+)/', $s, $m)) {
            return [$m[1], $m[2]]; // [prov, dist]
        }
        return null;
    };

    $asCu = fn($num) => ($num !== null && $num !== '') ? ('cu-' . trim((string)$num)) : null;

    $stats = ['total'=>0,'created'=>0,'updated'=>0,'errors'=>[]];

    foreach ($dataRows as $i => $row) {
        $stats['total']++;
        $excelRowNo = $headerRowIndex + 1 + ($i + 1); // để báo lỗi đúng dòng trong Excel

        $rawCode  = trim((string)($row[$colCode] ?? ''));
        $rawName  = trim((string)($row[$colName] ?? ''));
        if ($rawCode === '' || $rawName === '') {
            $stats['errors'][] = "Dòng {$excelRowNo}: thiếu mã hoặc tên.";
            continue;
        }

        $schoolTypeName = $colType  ? trim((string)($row[$colType] ?? ''))  : '';
        $levelName      = $colLevel ? trim((string)($row[$colLevel] ?? '')) : '';

        // --- Lấy mã địa giới thô từ Excel ---
        $wardCodeOldRaw = $colWardCodeOld ? trim((string)($row[$colWardCodeOld] ?? '')) : '';
        $distCodeOldRaw = $colDistCodeOld ? trim((string)($row[$colDistCodeOld] ?? '')) : '';
        $wardCodeNewRaw = $colWardCodeNew ? trim((string)($row[$colWardCodeNew] ?? '')) : '';

        // Kết quả chuẩn hoá để lưu
        $wardOld = null;   // 'cu-xxxxx'
        $distOld = null;   // 'cu-xxx'
        $wardNew = null;   // 'xxxxx' (mã mới)

        // 1) ƯU TIÊN: nếu "mã phường/xã CŨ" có 3 phần → lấy wardOld = cu-<phần 3>, distOld = cu-<phần 2>
        if ($trip = $extractTriplet($wardCodeOldRaw)) {
            [, $dist, $ward] = $trip;
            $wardOld = $asCu($ward);
            $distOld = $asCu($dist);
        } else {
            // 2) Nếu không, thử parse theo cách cũ (ví dụ cột chỉ chứa '09418')
            if ($wardCodeOldRaw !== '') {
                // Kỳ vọng GeoService trả về đúng 'cu-xxxxx'
                $wardOld = $geo->oldCodeFromExcel($wardCodeOldRaw, 3);
            }
        }

        // 3) Nếu có cột huyện CŨ riêng, ưu tiên update/điền distOld
        if ($distCodeOldRaw !== '') {
            if ($pair = $extractPair($distCodeOldRaw)) {
                [, $dist] = $pair;
                $distOld = $asCu($dist);
            } elseif ($trip2 = $extractTriplet($distCodeOldRaw)) {
                [, $dist, ] = $trip2;
                $distOld = $asCu($dist);
            } else {
                $distOld = $geo->oldCodeFromExcel($distCodeOldRaw, 2); // 'cu-xxx'
            }
        }

        // 4) Nếu đã có wardOld mà chưa có distOld → suy ra huyện CŨ từ xã CŨ
        if ($wardOld && !$distOld) {
            $distOld = $geo->oldDistrictCodeFromOldWard($wardOld); // 'cu-xxx' hoặc null
        }

        // 5) XỬ LÝ MÃ MỚI
        if ($wardCodeNewRaw !== '') {
            // File đã có sẵn mã mới
            $wardNew = $wardCodeNewRaw; // 'xxxxx'
            // Nếu thiếu CŨ mà bạn muốn suy ngược, có thể bật dòng dưới:
            // if (!$wardOld) { $wardOld = $geo->mapNewWardToOldCode($wardNew); }
        } else {
            // Chưa có mã mới → map từ mã CŨ đã chuẩn hoá
            if ($wardOld) {
                $wardNew = $geo->mapOldWardToNewCode($wardOld); // 'xxxxx' hoặc null
            }
        }

        // (Tuỳ chọn) Fallback theo tên khi thiếu mã — nặng, cân nhắc bật/tắt
        if (!$wardOld && $colWardName && !empty($row[$colWardName])) {
            $w = $geo->fuzzyFindOldByName((string)$row[$colWardName], 3);
            if ($w?->code) {
                $wardOld = $w->code; // kỳ vọng 'cu-xxxxx'
                if (!$distOld) {
                    $distOld = $geo->oldDistrictCodeFromOldWard($wardOld);
                }
                if (!$wardNew) {
                    $wardNew = $geo->mapOldWardToNewCode($wardOld);
                }
            }
        }
        if (!$distOld && $colDistrictName && !empty($row[$colDistrictName])) {
            $d = $geo->fuzzyFindOldByName((string)$row[$colDistrictName], 2);
            if ($d?->code) {
                $distOld = $d->code; // 'cu-xxx'
            }
        }

       try {
            $type  = $schoolTypeName ? \App\Models\SchoolType::firstOrCreate(['name'=>$schoolTypeName]) : null;
            $level = $levelName      ? \App\Models\EducationalLevel::firstOrCreate(['name'=>$levelName]) : null;

            // ⬇️ SỬA: dùng $geo (không phải $this->geo)
            if (!$wardNew && $wardOld) {
                $wardNew = $geo->newWardCodeFromOldWard($wardOld);
            }

            if ($distOld) {
                // suy ra tỉnh cũ từ huyện cũ (nếu bạn cần code)
                $province_code = $geo->unitFromCode($distOld)->parent->code ?? null;
            }
            if ($wardNew) {
                $province_code_new = $geo->unitFromCode($wardNew)->parent->code ?? null;
            }
            if (!$province_code && $wardOld) {
                $province_code = $geo->oldProvinceCodeFromOldWard($wardOld);
            }
            if (!$province_code_new && $province_code) {
                $province_code_new = $geo->newCodeFromOldCode($province_code);
            }

            // ====== ID tương ứng với code ======
            $oldWardId      = $geo->idByCode('old', $wardOld);
            $newWardId      = $geo->idByCode('new', $wardNew);
            $oldDistrictId  = $geo->idByCode('old', $distOld);
            $oldProvinceId  = $geo->idByCode('old', $province_code);
            $newProvinceId  = $geo->idByCode('new', $province_code_new);
            $villageId      = null; // nếu có cột mã thôn/ấp, parse tương tự rồi codeToId()

            $payload = [
                'code'                 => $rawCode,
                'name'                 => $rawName,
                'school_type_id'       => $type?->id,
                'educational_level_id' => $level?->id,

                // Code hệ cũ/mới (giữ nguyên như bạn đang lưu)
                'ward_code'            => $wardOld,
                'district_code'        => $distOld,
                'ward_code_new'        => $wardNew,
                'province_code'        => $province_code ?? null,
                'province_code_new'    => $province_code_new ?? null,

                // ⬇️ Thêm các ID để JOIN/report nhanh
                'old_ward_id'          => $oldWardId,
                'new_ward_id'          => $newWardId,
                'old_district_id'      => $oldDistrictId,
                'old_province_id'      => $oldProvinceId,
                'new_province_id'      => $newProvinceId,
                'village_id'           => $villageId??null,
            ];

            $school = \App\Models\School::where('code', $rawCode)->first();
            if ($school) {
                $school->update($payload);
                $stats['updated']++;
            } else {
                \App\Models\School::create($payload);
                $stats['created']++;
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (mb_strlen($msg) > 300) $msg = mb_substr($msg, 0, 300).'…';
            $stats['errors'][] = "Dòng {$excelRowNo}: ".$msg;
        }

        if (($stats['total'] % 1000) === 0) { usleep(10000); }
    }

    return $stats;
}


/**
 * Quét 1..$scan dòng đầu để tìm row header, trả về:
 *  [$map, $dataRows, $headerRowIndex]
 *  - $map: ['ma_truong' => 'A', 'ten_truong' => 'B', ...]
 */
private function extractHeader(array $rows, int $scan = 15): array
{
    // Các alias hợp lệ cho mỗi cột "chuẩn"
    $aliases = [
        'ma_truong'         => ['ma_truong','ma truong','mã trường','ma','code'],
        'ten_truong'        => ['ten_truong','ten truong','tên trường','name','ten'],
        'loai_hinh_truong'  => ['loai_hinh_truong','loai hinh truong','loai hinh','loai_hinh','loai'],
        'bac_hoc'           => ['bac_hoc','bac hoc','bậc học','cap hoc','cấp học'],
        'ten_phuong_xa'     => ['ten_phuong_xa','ten phuong xa','tên phường xã','ten xa','ten phuong'],
        'ten_quan_huyen'    => ['ten_quan_huyen','ten quan huyen','tên quận huyện','ten huyen','ten quan'],
        'ma_phuong_xa'      => ['ma_phuong_xa','ma phuong xa','mã phường xã','ma xa','ma phuong','ward_code_old'],
        'ma_quan_huyen'     => ['ma_quan_huyen','ma quan huyen','mã quận huyện','ma huyen','ma quan','district_code_old'],
        // NEW:
        'ma_phuong_xa_moi'  => ['ma_phuong_xa_moi','ma phuong xa moi','ma_phuong_xa_new','ma phuong xa new','ward_code_new','ma_px_moi','ma_px_new'],
    ];

    $max = min($scan, count($rows));
    for ($i = 0; $i < $max; $i++) {
        $candidate = $rows[$i] ?? [];
        if (!is_array($candidate)) continue;

        // Build map canonical -> column letter
        $map = [];
        foreach ($candidate as $colLetter => $label) {
            $norm = $this->normalizeHeaderKey((string)$label);
            if ($norm === '') continue;

            foreach ($aliases as $canonical => $vars) {
                if (in_array($norm, $vars, true) && empty($map[$canonical])) {
                    $map[$canonical] = $colLetter;
                }
            }
        }

        // Chỉ cần có 2 cột bắt buộc là ok
        if (!empty($map['ma_truong']) && !empty($map['ten_truong'])) {
            // Cắt bỏ phần header
            $dataRows = array_slice($rows, $i + 1);
            return [$map, $dataRows, $i];
        }
    }

    throw \Illuminate\Validation\ValidationException::withMessages([
        'file' => 'Không tìm thấy hàng tiêu đề phù hợp trong 15 dòng đầu.'
    ]);
}

/** Chuẩn hoá nhãn cột: hạ chữ thường, bỏ dấu, thay khoảng trắng bằng _ */
private function normalizeHeaderKey(string $label): string
{
    $s = trim(mb_strtolower($label));

    // Bỏ ký tự thừa
    $s = str_replace(['*','.',',',':',';','/','\\','(',')','[',']','{','}','|','"',"'",'`'],'', $s);
    // Chuẩn hoá khoảng trắng
    $s = preg_replace('/\s+/u', ' ', $s);
    // Bỏ dấu tiếng Việt đơn giản
    $from = ['à','á','ả','ã','ạ','ă','ằ','ắ','ẳ','ẵ','ặ','â','ầ','ấ','ẩ','ẫ','ậ','è','é','ẻ','ẽ','ẹ','ê','ề','ế','ể','ễ','ệ','ì','í','ỉ','ĩ','ị','ò','ó','ỏ','õ','ọ','ô','ồ','ố','ổ','ỗ','ộ','ơ','ờ','ớ','ở','ỡ','ợ','ù','ú','ủ','ũ','ụ','ư','ừ','ứ','ử','ữ','ự','ỳ','ý','ỷ','ỹ','ỵ','đ'];
    $to   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y','d'];
    $s = str_replace($from, $to, $s);

    // Chuẩn hóa phân cách
    $s = str_replace([' - ','-',' '], '_', $s);

    // Gom bớt biến thể phổ biến
    $s = preg_replace('/_+/','_', $s);
    return trim($s, '_');
}
 
/** Dò một dòng xem cột nào là gì. Trả về [map, matchedCount]. */
protected function scanHeaderRow(array $row): array
{
    $aliases = $this->headerAliases();

    $map = []; $matched = 0;
    foreach ($row as $colLetter => $text) {
        if ($text === null || $text === '') continue;

        $norm = $this->normalizeHeaderText((string)$text); // ví dụ: "ma truong"
        if ($norm === '') continue;

        foreach ($aliases as $key => $alts) {
            foreach ($alts as $alt) {
                // so khớp chính xác hoặc chứa (đề phòng “mã phường xã” có thêm ký tự)
                if ($norm === $alt || str_contains($norm, $alt)) {
                    if (!isset($map[$key])) {
                        $map[$key] = $colLetter;
                        $matched++;
                    }
                    break 2;
                }
            }
        }
    }
    return [$map, $matched];
}

/** Bảng đồng nghĩa cho các tiêu đề cột (đã hạ chuẩn không dấu, cách). */
protected function headerAliases(): array
{
    return [
        'ma_truong'         => ['ma truong', 'ma trg'],
        'ten_truong'        => ['ten truong', 'ten trg'],
        'loai_hinh_truong'  => ['loai hinh truong', 'loai hinh', 'loai truong'],
        'bac_hoc'           => ['bac hoc', 'cap hoc'],
        'ten_phuong_xa'     => ['ten phuong xa', 'phuong xa', 'ten px'],
        'ten_quan_huyen'    => ['ten quan huyen', 'quan huyen', 'ten qh'],
        'ma_phuong_xa'      => ['ma phuong xa', 'ma px', 'mapx'],
        'ma_quan_huyen'     => ['ma quan huyen', 'ma qh', 'maqh'],
        // nếu bạn có thêm cột khác, bổ sung ở đây
    ];
}

/** Chuẩn hoá text tiêu đề: lower, thay NBSP, bỏ dấu, chỉ còn chữ số/chữ cái cách nhau bằng 1 space. */
protected function normalizeHeaderText(string $s): string
{
    // thay NBSP & các khoảng trắng unicode
    $s = str_replace(["\u{00A0}", "\xC2\xA0"], ' ', $s);
    $s = mb_strtolower(trim($s));

    // bỏ dấu tiếng Việt (lower trước nên chỉ cần bảng thường)
    $s = strtr($s, [
        'đ'=>'d','ă'=>'a','â'=>'a','á'=>'a','à'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
        'ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
        'ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
        'ê'=>'e','é'=>'e','è'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
        'ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
        'ô'=>'o','ơ'=>'o','ó'=>'o','ò'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
        'ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
        'ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
        'ư'=>'u','ú'=>'u','ù'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
        'ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
        'í'=>'i','ì'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
        'ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
    ]);

    // chỉ giữ a-z0-9 và khoảng trắng -> nén còn 1 space
    $s = preg_replace('~[^a-z0-9]+~u', ' ', $s);
    $s = preg_replace('~\s+~u', ' ', trim($s));
    return $s;
}
    /**
     * Tạo mới 1 trường học.
     * @throws AuthorizationException|\Illuminate\Validation\ValidationException
     */
     public function create(array $data): School
    {
        $this->permissionService->requireAdmin(\Illuminate\Support\Facades\Auth::user());

        if (School::where('code', $data['code'] ?? null)->exists()) {
            throw ValidationException::withMessages(['code' => 'Mã trường đã tồn tại.']);
        }

        // Ít nhất phải có 1 trong 2: cũ hoặc mới
        if (empty($data['ward_code']) && empty($data['ward_code_new'])) {
            throw ValidationException::withMessages(['ward_code_new' => 'Hãy chọn địa chỉ Cũ hoặc Mới.']);
        }

        // Chuẩn hóa từ input
        $wardOld = $data['ward_code']      ?? null;
        $wardNew = $data['ward_code_new']  ?? null;
        $distOld = $data['district_code']  ?? null;

        if ($wardNew && !$wardOld) {
            // NEW -> OLD
            $wardOld = $this->geo->mapNewWardToOldCode($wardNew);
            if ($wardOld && !$distOld) {
                $distOld = $this->geo->oldDistrictCodeFromOldWard($wardOld);
            }
        }

        if ($wardOld && !$wardNew) {
            // OLD -> NEW
            $wardNew = $this->geo->mapOldWardToNewCode($wardOld);
        }
        
        if(!$wardNew) {
            $wardNew = $this->geo->newWardCodeFromOldWard($wardOld);
        }

        $province_code = $this->geo->unitFromCode($distOld)->parent->code ?? null;
        $province_code_new = $this->geo->unitFromCode($wardNew)->parent->code ?? null;
        if(!$province_code) {
            $province_code = $this->geo->oldProvinceCodeFromOldWard($wardOld);
        }
        
        if(!$province_code_new) {
            $province_code_new = $this->geo->newCodeFromOldCode($province_code);
        }
        
        if ($wardOld && !$distOld) {
            $distOld = $this->geo->oldDistrictCodeFromOldWard($wardOld);
        }
// cho nay can thêm id vao
        $geo = $this->geo;
        $oldWardId      = $geo->idByCode('old', $wardOld);
        $newWardId      = $geo->idByCode('new', $wardNew);
        $oldDistrictId  = $geo->idByCode('old', $distOld);
        $oldProvinceId  = $geo->idByCode('old', $province_code);
        $newProvinceId  = $geo->idByCode('new', $province_code_new);

        return School::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'school_type_id' => $data['school_type_id'] ?? null,
            'educational_level_id' => $data['educational_level_id'] ?? null,
            'ward_code'      => $wardOld,
            'district_code'  => $distOld ?: ($wardOld ? $this->geo->oldDistrictCodeFromOldWard($wardOld) : null),
            'ward_code_new'  => $wardNew,
            'province_code' => $province_code ?? null,
            'province_code_new' => $province_code_new ?? null,
            'is_dissolved'   => $data['is_dissolved'] ?? null,

                // ⬇️ Thêm các ID để JOIN/report nhanh
                'old_ward_id'          => $oldWardId,
                'new_ward_id'          => $newWardId,
                'old_district_id'      => $oldDistrictId,
                'old_province_id'      => $oldProvinceId,
                'new_province_id'      => $newProvinceId,
                'village_id'           => $villageId??null,
        ]);
    }

    public function update(School $school, array $data): void
    {
        $this->permissionService->requireAdmin(\Illuminate\Support\Facades\Auth::user());

        if (!empty($data['code']) && $data['code'] !== $school->code) {
            if (School::where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'Mã trường đã tồn tại.']);
            }
        }

        $wardOld = $data['ward_code']      ?? $school->ward_code;
        $wardNew = $data['ward_code_new']  ?? $school->ward_code_new;
        $distOld = $data['district_code']  ?? $school->district_code;

        if ($wardNew && !$wardOld) {
            $wardOld = $this->geo->mapNewWardToOldCode($wardNew);
            if ($wardOld && !$distOld) {
                $distOld = $this->geo->oldDistrictCodeFromOldWard($wardOld);
            }
        }
        if ($wardOld && !$wardNew) {
            $wardNew = $this->geo->mapOldWardToNewCode($wardOld);
        }
       
        
        if ($wardOld && !$distOld) {
            $distOld = $this->geo->oldDistrictCodeFromOldWard($wardOld);
        }

         // code mới không có, thử suy ngược từ cũ
         if(!$wardNew) {
            $wardNew = $this->geo->newWardCodeFromOldWard($wardOld);
        }
        $province_code = $this->geo->unitFromCode($distOld)->parent->code ?? null;
        $province_code_new = $this->geo->unitFromCode($wardNew)->parent->code ?? null;
        if(!$province_code) {
            $province_code = $this->geo->oldProvinceCodeFromOldWard($wardOld);
        }
        
        if(!$province_code_new) {
            $province_code_new = $this->geo->newCodeFromOldCode($province_code);
        }
// cho nay cung can them id vao
        $geo = $this->geo;
        $oldWardId      = $geo->idByCode('old', $wardOld);
        $newWardId      = $geo->idByCode('new', $wardNew);
        $oldDistrictId  = $geo->idByCode('old', $distOld);
        $oldProvinceId  = $geo->idByCode('old', $province_code);
        $newProvinceId  = $geo->idByCode('new', $province_code_new);

        $school->update([
            'code' => $data['code'] ?? $school->code,
            'name' => $data['name'] ?? $school->name,
            'school_type_id' => $data['school_type_id'] ?? $school->school_type_id,
            'educational_level_id' => $data['educational_level_id'] ?? $school->educational_level_id,
            'ward_code'      => $wardOld,
            'district_code'  => $distOld ?: ($wardOld ? $this->geo->oldDistrictCodeFromOldWard($wardOld) : null),
            'ward_code_new'  => $wardNew,
            'province_code' => $province_code ?? null,
            'province_code_new' => $province_code_new ?? null,

                // ⬇️ Thêm các ID để JOIN/report nhanh
                'old_ward_id'          => $oldWardId,
                'new_ward_id'          => $newWardId,
                'old_district_id'      => $oldDistrictId,
                'old_province_id'      => $oldProvinceId,
                'new_province_id'      => $newProvinceId,
                'village_id'           => $villageId??null,

        ]);
    }
    /**
     * Xoá 1 trường học.
     * @param  School|int  $school
     * @throws AuthorizationException
     */
    public function delete( School|int $school): void
    {
        $this->permissionService->requireAdmin(\Illuminate\Support\Facades\Auth::user());

        $school = $school instanceof School ? $school : School::findOrFail($school);

        DB::transaction(function () use ($school) {
            // Nếu có ràng buộc areas... có thể kiểm tra trước ở đây (optional)
            $school->delete();
        });
    }

    /* ================== Helpers ================== */


    /**
     * Validate & chuẩn hoá dữ liệu đầu vào.
     * @param  array     $input
     * @param  int|null  $ignoreId  (khi update)
     * @return array   dữ liệu đã validate
     */
    protected function validate(array $input, ?int $ignoreId = null): array
    {
        $rules = [
            'code' => [
                'required','string','max:50',
                Rule::unique('schools','code')->ignore($ignoreId),
            ],
            'name' => ['required','string','max:255'],

            'school_type_id' => ['required','integer','exists:school_types,id'],
            'educational_level_id' => ['required','integer','exists:educational_levels,id'],

            // Lưu bằng code hành chính (có thể là mã cũ có tiền tố "cu")
            'ward_code'     => ['nullable','string','max:50'],
            'district_code' => ['nullable','string','max:50'],
        ];

        // Chỉ nhận những field hợp lệ
        $input = array_intersect_key($input, array_flip(array_keys($rules)));

        // Trim chuỗi
        foreach (['code','name','ward_code','district_code'] as $k) {
            if (array_key_exists($k, $input) && is_string($input[$k])) {
                $input[$k] = trim($input[$k]);
            }
        }

        return Validator::validate($input, $rules);
    }
}
