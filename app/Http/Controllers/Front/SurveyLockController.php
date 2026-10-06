<?php
// app/Http/Controllers/Admin/SurveyLockController.php
namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\SurveyLock;
use App\Services\SurveyLockService;
use App\Services\GeoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use App\Services\SurveyLockLabelService;
use App\Services\AccessGuardService;

class SurveyLockController extends Controller
{
    public function index(Request $request,SurveyLockLabelService $labeler)
    {
        $locks = \App\Models\SurveyLock::with('creator')
            ->latest('id')
            ->get()
            ->each(fn($lk) => $labeler->decorate($lk));
        // dd($locks);
        return view('front.survey-lock.index', compact('locks'));
    }

     public function store(Request $request, SurveyLockService $locksSvc, AccessGuardService $guard)
    {
        
        // 1) Nhận scope + geo (province/ward/village), bỏ district
        $data = $request->validate([
            'scope'               => ['required', Rule::in(['SURVEY','STATS_SEND','STATS_INPUT'])],
            'geo.province_id'     => ['nullable','integer'],
            'geo.ward_id'         => ['nullable','integer'],
            'geo.village_id'      => ['nullable','integer'],
            'note'                => ['nullable','string','max:1000'],
        ]);
      
          // 1) Kiểm tra quyền + ép phạm vi theo user
        $ctx = $guard->enforce($request, 'khoa_du_lieu', 'create');
//   dd($request->all());
        // // 2) Bị khóa SURVEY ở geo hiện tại thì chặn
        // $guard->assertUnlockedOrAbort('SURVEY', $r);
        
        // 2) Suy ra level + geo_id theo thứ tự: VILLAGE -> WARD -> PROVINCE -> GLOBAL
        //    (dùng service nếu bạn đã thêm các hàm resolve... trước đó)
        if (method_exists($locksSvc, 'resolveLevelFromArray')) {
            [$geoLevel, $geoId] = $locksSvc->resolveLevelFromArray($data['geo'] ?? []);
        } else {
            // fallback inline nếu Service chưa có hàm resolve...
            $toInt = fn($v) => ($v === null || $v === '') ? null : ((int)$v ?: null);
            $p = $toInt($data['geo']['province_id'] ?? null);
            $w = $toInt($data['geo']['ward_id'] ?? null);
            $v = $toInt($data['geo']['village_id'] ?? null);

            if ($v)      { $geoLevel = 'VILLAGE'; $geoId = $v; }
            elseif ($w)  { $geoLevel = 'WARD';    $geoId = $w; }
            elseif ($p)  { $geoLevel = 'PROVINCE';$geoId = $p; }
            else         { $geoLevel = 'GLOBAL';  $geoId = null; }
        }

        // 3) Lưu/ghi đè theo (scope, geo_level, geo_id), luôn bật is_active, không lưu thời gian
        $lock = SurveyLock::updateOrCreate(
            [
                'scope'     => $data['scope'],
                'geo_level' => $geoLevel,    // GLOBAL / PROVINCE / WARD / VILLAGE
                'geo_id'    => $geoId,       // null nếu GLOBAL
            ],
            [
                'is_active' => true,                         // hiệu lực ngay
                'note'      => $data['note'] ?? null,
                'locked_by' => $request->user()->id ?? null,
            ]
        );

        // 4) Xóa cache liên quan (nếu Service của bạn có)
        if (method_exists($locksSvc, 'forget')) {
            $locksSvc->forget($lock->scope, $lock->geo_level, $lock->geo_id);
        }

        return back()->with('status', 'Đã lưu khóa: '.$lock->scope.' / '.$lock->geo_level.($lock->geo_id ? ' #'.$lock->geo_id : ''));
    }

      public function toggle(SurveyLock $lock, SurveyLockService $locksSvc)
    {
        
        if( auth()->id() == $lock->locked_by || auth()->user()->role_id == 1 ||  auth()->user()->role_id==2)
        {
            $lock->is_active = !$lock->is_active;
            $lock->save();

            $locksSvc->forget($lock->scope, $lock->geo_level, $lock->geo_id);

            return back()->with('status', $lock->is_active ? 'Đã bật khóa.' : 'Đã tắt khóa.');
        }
        else
        {
              return redirect()->back()->with('error',   'Bạn không có quyền chỉnh sửa.');
        }
       
    }

    public function destroy(SurveyLock $lock, SurveyLockService $locksSvc)
    {
       
        if(auth()->id() == $lock->locked_by || auth()->user()->role_id == 1 ||  auth()->user()->role_id==2)
        {
            $locksSvc->forget($lock->scope, $lock->geo_level, $lock->geo_id);
            $lock->delete();

            return back()->with('status', 'Đã xoá khóa.');
        }
         else
        {
              return back()->with('error',   'Bạn không có quyền chỉnh sửa.');
        }
    }

    /* ---------------- helpers ---------------- */

    private function toInt($v): ?int
    {
        if ($v === null || $v === '') return null;
        $n = (int) $v;
        return $n > 0 ? $n : null;
    }

    private function detectLevel(?int $provinceId, ?int $wardId, ?int $villageId): array
    {
        if ($villageId) return ['VILLAGE', $villageId];
        if ($wardId)    return ['WARD',    $wardId];
        if ($provinceId)return ['PROVINCE',$provinceId];
        return ['GLOBAL', null];
    }
}
