<?php
// app/Http/Controllers/Admin/GeoController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeoUnit;
use App\Models\GeoUnitMapping;
use Illuminate\Http\Request;
use App\Services\GeoService;
class GeoController extends Controller
{
    // ... provinces(), children(), v.v. giữ nguyên

    /** Trả về các đơn vị CŨ (old) ánh xạ từ một đơn vị MỚI (new) */
 public function resolve(Request $r)
    {
        $r->validate([
            'scheme' => 'required|in:old,new',
            'code'   => 'required|string',
        ]);

        $scheme = $r->scheme;
        $code   = $r->code;

        $u = GeoUnit::where('scheme',$scheme)->where('code',$code)->first();
        if (!$u) return response()->json(['message'=>'Not found'], 404);

        if ($scheme === 'old') {
            // old: Lv3->parent(Lv2)->parent(Lv1)
            $prov=null; $dist=null; $ward=null;
            if ($u->type_level == 3) {
                $ward = $u;
                $dist = GeoUnit::find($u->parent_id);
                $prov = $dist ? GeoUnit::find($dist->parent_id) : null;
            } elseif ($u->type_level == 2) {
                $dist = $u;
                $prov = GeoUnit::find($u->parent_id);
            } elseif ($u->type_level == 1) {
                $prov = $u;
            }

            return [
                'scheme' => 'old',
                'province' => $prov ? ['id'=>$prov->id,'code'=>$prov->code,'name'=>$prov->name] : null,
                'district' => $dist ? ['id'=>$dist->id,'code'=>$dist->code,'name'=>$dist->name] : null,
                'ward'     => $ward ? ['id'=>$ward->id,'code'=>$ward->code,'name'=>$ward->name] : null,
            ];
        } else {
            // new: Lv3->parent(Lv1) (không có Lv2)
            $prov=null; $ward=null;
            if ($u->type_level == 3) {
                $ward = $u;
                $prov = GeoUnit::find($u->parent_id);
            } elseif ($u->type_level == 1) {
                $prov = $u;
            }

            return [
                'scheme' => 'new',
                'province' => $prov ? ['id'=>$prov->id,'code'=>$prov->code,'name'=>$prov->name] : null,
                'ward'     => $ward ? ['id'=>$ward->id,'code'=>$ward->code,'name'=>$ward->name] : null,
            ];
        }
    }
    public function provinces(Request $req)
    {
        $scheme = $req->get('scheme','old');
        return GeoUnit::where('scheme',$scheme)
            ->where('type_level',1)
            ->orderBy('name')
            ->get(['id','code','name']);
    }

    public function children($parentId)
    {
        return ['children' => GeoUnit::where('parent_id',(int)$parentId)
            ->orderBy('name')->get(['id','code','name'])];
    }

    // NEW -> OLD (lấy cả ward cũ + district cũ)
    public function mapNewWard(Request $req, GeoService $geo)
    {
        $code = trim((string)$req->get('code',''));
        if ($code === '') return ['ward_old_code'=>null,'district_old_code'=>null];

        return $geo->mapNewWardToOldCodes($code); // dùng helper mới ở trên
    }

    // OLD -> NEW
    public function mapOldWard(Request $req, GeoService $geo)
    {
        $code = trim((string)$req->get('code',''));
        if ($code === '') return ['ward_new_code'=>null];

        return ['ward_new_code' => $geo->mapOldWardToNewCode($code)];
    }

    public function oldByNew(int $newId)
    {
        $new = GeoUnit::with('parent')->findOrFail($newId);

        // Chấp nhận vài loại quan hệ phổ biến
        $rels = ['successor','unchanged','merged_into','split_from'];

        $olds = GeoUnit::query()
            ->select('geo_units.*')
            ->join('geo_unit_mappings as m','m.old_unit_id','=','geo_units.id')
            ->where('m.new_unit_id', $newId)
            ->whereIn('m.relation', $rels)
            ->with('parent')
            ->get()
            ->map(fn($o)=>[
                'id'   => $o->id,
                'code' => $o->code,
                'name' => $o->name,
                'parent' => $o->parent ? [
                    'id' => $o->parent->id,
                    'code' => $o->parent->code,
                    'name' => $o->parent->name,
                ] : null,
            ])->values();

        return response()->json([
            'ok'  => true,
            'new' => ['id'=>$new->id, 'name'=>$new->name, 'code'=>$new->code],
            'olds'=> $olds,
        ]);
    }
     public function provincesSmart(Request $r)
    {
        $scheme = $r->get('scheme','old');

        // ưu tiên có sẵn cấp 1
        $real = GeoUnit::query()
            ->where('scheme', $scheme)
            ->where('type_level', 1)
            ->orderBy('name')
            ->get(['id','code','name']);
        if ($real->isNotEmpty()) {
            return $real;
        }

        // fallback: lấy từ cấp 2/3 theo prefix code
        $codes = GeoUnit::query()
            ->where('scheme',$scheme)
            ->whereIn('type_level',[2,3])
            ->pluck('code');

        $prefixes = [];
        foreach ($codes as $code) {
            if (!$code) continue;
            // old: cu_66_... -> "cu_66"
            // new: 66_...    -> "66"
            if (str_starts_with($code, 'cu_')) {
                $parts = explode('_', $code);
                if (count($parts) >= 2) {
                    $prefix = $parts[0].'_'.$parts[1]; // cu_66
                } else {
                    $prefix = $code;
                }
            } else {
                $parts = explode('_', $code);
                $prefix = $parts[0]; // 66
            }
            $prefixes[$prefix] = true;
        }

        // trả về mảng “giả tỉnh”: id=null, name dùng code (đủ để filter)
        $items = collect(array_keys($prefixes))
            ->sort()
            ->values()
            ->map(fn($c)=>['id'=>null,'code'=>$c,'name'=>$c]);

        return response()->json($items);
    }

    // Lấy list theo prefix tỉnh: dùng để dựng dropdown cấp 2 (cũ) hoặc cấp 3 (mới)
    public function listByPrefix(Request $r)
    {
        $scheme   = $r->get('scheme','old');       // old|new
        $level    = (int) $r->get('level', 2);     // 2 (district old) or 3 (ward new)
        $provCode = $r->get('prov_code');          // cu_66 hoặc 66

        if (!$provCode) return response()->json(['children'=>[]]);

        $items = GeoUnit::query()
            ->where('scheme', $scheme)
            ->where('type_level', $level)
            ->where('code', 'like', $provCode.'_%')
            ->orderBy('name')
            ->get(['id','code','name','type_level','parent_id']);

        return response()->json(['children' => $items]);
    }
}
