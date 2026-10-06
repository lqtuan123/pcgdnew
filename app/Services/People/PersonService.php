<?php

namespace App\Services\People;

use App\Models\Person;
use Illuminate\Support\Str;
use App\Services\Catalogs\EthnicityService;
use App\Services\Catalogs\ReligionService;
use App\Services\Catalogs\PriorityCategoryService;
use App\Services\People\PersonResidenceService;
use Illuminate\Validation\ValidationException;
use App\Services\GeoService;
use Illuminate\Support\Facades\Log;
class PersonService
{
     public function __construct(
        protected EthnicityService $ethnic,
        protected ReligionService $religion,
        protected PriorityCategoryService $priority,
        protected GeoService $geoService,
        protected PersonResidenceService $residenceService,
    ) {}
    /** Chuẩn hoá chuỗi: trim, bỏ khoảng trắng thừa */
    private function t(?string $s): ?string
    {
        if (!isset($s)) return null;
        $s = trim($s);
        return $s === '' ? null : $s;
    }

    /** Chuẩn hoá số nguyên nhẹ */
    private function i($v): ?int
    {
        if ($v === '' || $v === null) return null;
        if (is_numeric($v)) return (int)$v;
        $v = preg_replace('/\D+/', '', (string)$v);
        return $v === '' ? null : (int)$v;
    }

    /** Chuẩn hoá boolean: cho phép true/false, 1/0, x, có/không, ✓ */
    private function b($v): bool
    {
        $raw = (string)($v ?? '');
        $s = Str::of($raw)->lower()
            ->ascii()                      // bỏ dấu "có" -> "co"
            ->trim();

        if ($raw !== '' && (Str::contains($raw, '✓') || Str::contains($raw, '✔'))) return true;

        return in_array((string)$s, ['1','x','y','yes','true','co'], true);
    }

    /** Ghép địa bàn vào attrs theo scheme */
    private function geoAttrs(string $scheme, array $geo): array
    {
        $out = [];
        if ($scheme === 'old') {
            $out['old_ward_id']     = $geo['ward_id']     ?? null;
            $out['old_district_id'] = $geo['district_id'] ?? null;
        } else {
            $out['new_ward_id']     = $geo['ward_id']     ?? null;
        }
        return $out;
    }

    /** Map 1 dòng Excel -> attributes của Person */
    private function mapRowToAttributes(array $row, string $scheme, array $geo): array
    {
        $attrs = [
            'ho_dem'     => $this->t($row['ho_dem'] ?? null),
            'ten'        => $this->t($row['ten'] ?? null),

            'dob_day'    => $this->i($row['dob_day'] ?? null),
            'dob_month'  => $this->i($row['dob_month'] ?? null),
            'dob_year'   => $this->i($row['dob_year'] ?? null),

            'is_female'  => $this->b($row['is_female'] ?? false),

            'identity_number' => $this->t($row['identity_number'] ?? null),
            'phone'           => $this->t($row['contact_phone'] ?? ($row['phone'] ?? null)),
            'addressline'     => $this->t($row['addressline'] ?? null),

            // Nếu bạn đã có bảng dân tộc/tôn giáo và mapping string->id,
            // có thể xử lý ở đây. Tạm thời để null:
            // 'ethnicity_id' => $this->resolveEthnicityId($row['ethnicity'] ?? null),
            // 'religion_id'  => $this->resolveReligionId($row['religion'] ?? null),
        ];

        // Gộp địa bàn theo scheme
        $attrs = array_merge($attrs, $this->geoAttrs($scheme, $geo));
        // dd($row, $attrs);
        return $attrs;
    }

    /** Tìm người đã tồn tại theo các quy tắc trùng */
   private function matchExisting(array $attrs): ?Person
    {
        // --- 0) Chuẩn hoá đầu vào ---
        $id        = trim((string)($attrs['identity_number'] ?? ''));
        $soPhieu   = trim((string)($attrs['so_phieu'] ?? ''));
        $dobYear   = isset($attrs['dob_year']) ? (int)$attrs['dob_year'] : null;
        $dobMonth   = isset($attrs['dob_month']) ? (int)$attrs['dob_month'] : null;
        $dobDay   = isset($attrs['dob_day']) ? (int)$attrs['dob_day'] : null;
        $hoDemIn   = $this->vnNormalize((string)($attrs['ho_dem'] ?? ''));
        $tenIn     = $this->vnNormalize((string)($attrs['ten'] ?? ''));

        // --- 1) CMND/CCCD (so khớp trực tiếp) ---
        if ($id !== '') {
            $m = Person::where('identity_number', $id)->first();
            if ($m) return $m;
        }

        // --- 2) Số phiếu + Năm sinh: lấy ứng viên rồi so khớp trong PHP ---
        if ($soPhieu !== '' && $dobYear) {
            // Chỉ lấy trường cần thiết để nhẹ dữ liệu
            $candidates = Person::query()
                ->where('so_phieu', $soPhieu)
                ->where('dob_year', $dobYear)->where('dob_day', $dobDay)->where('dob_month', $dobMonth)
                ->get(['id','so_phieu','ho_dem','ten','dob_year']);

            // 2.1) So khớp nghiêm ngặt: phân biệt dấu & hoa/thường
            foreach ($candidates as $p) {
                if (
                    $this->vnEqual($this->vnNormalize($p->ho_dem ?? ''), $hoDemIn, true) &&
                    $this->vnEqual($this->vnNormalize($p->ten ?? ''),    $tenIn,   true)
                ) {
                    return $p;
                }
            }

            // 2.2) Nếu chưa khớp: thử CI (không phân biệt hoa/thường), vẫn phân biệt dấu
            foreach ($candidates as $p) {
                if (
                    $this->vnEqual($this->vnNormalize($p->ho_dem ?? ''), $hoDemIn, false) &&
                    $this->vnEqual($this->vnNormalize($p->ten ?? ''),    $tenIn,   false)
                ) {
                    return $p;
                }
            }

            // (Tuỳ chọn) Ghi log để debug vì sao không khớp
            \Log::debug('No exact match; dump candidates', [
                'input' => compact('soPhieu','dobYear','hoDemIn','tenIn'),
                'candidates' => $candidates->map(function($p){
                    return [
                        'id'      => $p->id,
                        'ho_dem'  => $p->ho_dem,
                        'ten'     => $p->ten,
                        'dob_year'=> $p->dob_year,
                    ];
                })->all(),
            ]);
        }

        return null;
    }

    /**
     * Chuẩn hoá chuỗi tiếng Việt để so sánh nhị phân:
     * - Chuẩn hoá Unicode (NFC) nếu có ext-intl
     * - Cắt & nén khoảng trắng
     * - KHÔNG bỏ dấu (để phân biệt "Anh" vs "Ánh")
     */
    private function vnNormalize(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        if (class_exists('\Normalizer')) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C);
        }
        return $s;
    }

    /**
     * So sánh nhị phân, có tuỳ chọn phân biệt hoa/thường.
     * Luôn phân biệt DẤU vì ta không loại bỏ dấu ở bước normalize.
     */
    private function vnEqual(string $a, string $b, bool $caseSensitive = true): bool
    {
        if (!$caseSensitive) {
            $a = mb_strtolower($a, 'UTF-8');
            $b = mb_strtolower($b, 'UTF-8');
        }
        return strcmp($a, $b) === 0;
    }

    /**
     * Upsert 1 người từ dòng Excel
     *   - $row: dữ liệu đã mapping từ UI
     *   - $scheme, $geo: để set đúng ward/district
     */
    public function upsertFromRow(array $row, string $scheme, array $geo = []): Person
    {
       Log::info('upsertFromRow');
       Log::info($geo);
        // --- A) Kiểm tra đầu vào mức "dòng" ---
        $errors   = [];
        $ten      = trim((string)($row['ten'] ?? ''));
        $hoDem    = trim((string)($row['ho_dem'] ?? ''));
        // $dobYearS = trim((string)($row['dob_year'] ?? ''));
        // echo $ten.'<br/>';
        if ($ten === '') {
            $errors['ten'][] = 'Thiếu tên (cột "Tên").';
        }
        // if ($dobYearS === '') {
        //     $errors['dob_year'][] = 'Thiếu năm sinh.'.$dobYearS;
        // } elseif (!preg_match('/^\d{4}$/', $dobYearS)) {
        //     $errors['dob_year'][] = 'Năm sinh không hợp lệ (phải 4 chữ số).'.$dobYearS;
        // }

        // B) Quy chiếu danh mục + kiểm tra tên có nhưng không resolve được
        $ethName = trim((string)($row['ethnicity'] ?? ''));
        $relName = trim((string)($row['religion']  ?? ''));
        $priName = trim((string)($row['priority_name'] ?? '')); // diện ưu tiên

        $ethnicityId = $this->ethnic->resolveIdByName($ethName ?: null);
        $religionId  = $this->religion->resolveIdByName($relName ?: null);
        $priorityId  = $this->priority->resolveIdByName($priName ?: null);

        if ($ethName !== '' && !$ethnicityId)  $errors['ethnicity'][]      = "Không tìm thấy dân tộc: {$ethName}.";
        if ($relName !== '' && !$religionId)   $errors['religion'][]       = "Không tìm thấy tôn giáo: {$relName}.";
        if ($priName !== '' && !$priorityId)   $errors['priority_name'][]  = "Không tìm thấy diện ưu tiên: {$priName}.";

           $residence_status_id = $this->residenceService->resolveStatus($row['residence_status_name'] ?? null);
         $residence_type_id = $this->residenceService->resolveType($row['residence_type_name'] ?? null);

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        // C) Chuẩn hoá kiểu (tránh lỗi kiểu dữ liệu khi lưu)
        // $dobYear = (int)$row['dob_year'];

        // D) Tìm người cũ
        $existing = $this->matchExisting($row, $scheme, $geo);

        // E) Build attrs
        $newWard_id = $scheme === 'new' ? ($geo['ward_id'] ?? null) : null;
        $new_province_id = $scheme === 'new' ? ($geo['province_id'] ?? null) : null;
        if($scheme === 'old')
        {
            $newWard_id = $this->geoService->mapOldIdToNewId($geo['ward_id'] ?? null);
            $new_province_id = $this->geoService->mapOldIdToNewId($geo['province_id'] ?? null);
        }


        $attrs = [
            'ho_dem'    => $hoDem !== '' ? $hoDem : null,
            'ten'       => $ten,
            'dob_day'   => $row['dob_day']   ?? null,
            'dob_month' => $row['dob_month'] ?? null,
            'dob_year'  => $row['dob_year']  ?? null,
            'so_phieu'  => $row['so_phieu']  ?? null,
            'contact_name'  => $row['contact_name']  ?? null,
            'note'  => $row['note']  ?? null,
            'residence_status_id' => $residence_status_id,
            'residence_type_id' => $residence_type_id,
            'is_female' => (bool)($row['is_female'] ?? false),

            'identity_number' => $row['identity_number'] ?? null,
            'phone'           => $row['contact_phone']   ?? ($row['phone'] ?? null),
            'addressline'     => $row['addressline']     ?? null,

            'old_ward_id'     => $scheme === 'old' ? ($geo['ward_id']     ?? null) : null,
            'old_district_id' => $scheme === 'old' ? ($geo['district_id'] ?? null) : null,
            'new_ward_id'     => $newWard_id,
            'village_id'      => $geo['village_id'] ?? null,
            'old_province_id' => $scheme === 'old' ? ($geo['province_id'] ?? null) : null,
            'new_province_id' =>  $new_province_id,

            'ethnicity_id'         => $ethnicityId ?: null,
            'religion_id'          => $religionId  ?: null,
            'priority_category_id' => $priorityId  ?: null,
        ];

        // Chuẩn hoá các cột FK rỗng => null (tránh '' gây lỗi integer)
        foreach (['old_ward_id','old_district_id','new_ward_id','ethnicity_id','religion_id','priority_category_id'] as $k) {
            if ($attrs[$k] === '' || $attrs[$k] === 0) $attrs[$k] = null;
        }

        // F) Lưu (bọc try–catch để convert mọi DB error -> ValidationException)
        try {
            if ($existing) {
                Log::info('exitting-------');
                $existing->fill(array_filter($attrs, fn($v) => !is_null($v)));
                $existing->save();
                return $existing->fresh();
            }
            Log::info('not exitting-------');
            $p = new Person();
            $p->fill($attrs);
            $p->save();

            return $p;
        } catch (QueryException $qe) {
            // Chuyển thông điệp SQL thành lỗi dễ hiểu cho người nhập
            throw ValidationException::withMessages([
                'database' => [ $this->prettyDbError($qe, 'người', $attrs) ],
            ]);
        } catch (Throwable $e) {
            // Bất kỳ lỗi PHP khác
            throw ValidationException::withMessages([
                'exception' => [ 'Lỗi không xác định khi lưu người: '.$e->getMessage() ],
            ]);
        }
    }

    /**
     * Rút gọn và “dịch” lỗi DB thành câu dễ hiểu.
     */
    protected function prettyDbError(QueryException $e, string $entityLabel, array $attrs): string
    {
        $info   = $e->errorInfo;              // [SQLSTATE, code, message]
        $code   = (int)($info[1] ?? $e->getCode());
        $msg    = (string)($info[2] ?? $e->getMessage());

        // MySQL: 1406 | PG: 22001 => Data too long
        if (str_contains($msg, 'Data too long') || in_array($code, [1406, 22001], true)) {
            $col = $this->extractColumn($msg);
            return 'Giá trị quá dài'.($col ? " cho cột '{$col}'" : '').' — vui lòng rút ngắn.';
        }

        // MySQL: 1048 | PG: 23502 => Column cannot be null
        if (str_contains($msg, 'cannot be null') || in_array($code, [1048, 23502], true)) {
            $col = $this->extractColumn($msg);
            return 'Thiếu dữ liệu bắt buộc'.($col ? " ở cột '{$col}'" : '').'.';
        }

        // MySQL: 1452 | PG: 23503 => FK violation
        if (str_contains($msg, 'foreign key') || in_array($code, [1452, 23503], true)) {
            $col = $this->extractColumn($msg);
            return 'Tham chiếu danh mục không hợp lệ'.($col ? " (cột '{$col}')" : '').'.';
        }

        // MySQL: 1366 | PG: 22P02/22007 => Sai kiểu/sai định dạng
        if (str_contains($msg, 'Incorrect') || in_array($code, [1366, 22007], true)) {
            $col = $this->extractColumn($msg);
            return 'Sai kiểu dữ liệu/định dạng'.($col ? " ở cột '{$col}'" : '').'.';
        }

        // fallback
        return "Không lưu được {$entityLabel}: ".preg_replace('/\s+/', ' ', $msg);
    }

    /**
     * Cố gắng bắt tên cột từ thông điệp DB phổ biến.
     */
    protected function extractColumn(string $msg): ?string
    {
        // MySQL: "for column 'colname' at row 1"
        if (preg_match("/column '([^']+)'/i", $msg, $m)) return $m[1];
        // PG: "null value in column \"colname\" violates not-null constraint"
        if (preg_match('/column "([^"]+)"/i', $msg, $m)) return $m[1];
        return null;
    }

    /** Helper hiển thị họ tên */
    public function fullName(Person $p): string
    {
        return trim(($p->ho_dem ? $p->ho_dem.' ' : '').($p->ten ?? ''));
    }
}
