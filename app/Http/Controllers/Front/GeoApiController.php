<?php

namespace App\Http\Controllers\Front;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GeoUnit;

class GeoApiController extends Controller
{
    /**
     * GET /geo/provinces?scheme=old|new
     * Trả danh sách cấp 1 (tỉnh/thành) theo scheme.
     */
    public function provinces(Request $request)
    {
        $scheme = $request->query('scheme', 'old');

        $items = GeoUnit::query()
            ->where('scheme', $scheme)
            ->where('type_level', 1)
            ->orderBy('name')
            ->get(['id','code','name']);

        return response()->json($items, 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * GET /geo/children/{unit}
     * Trả CON trực tiếp theo parent_id (chuẩn cho cả sơ đồ cũ và mới).
     */
    public function children(GeoUnit $unit)
    {
        $children = GeoUnit::query()
            ->where('parent_id', $unit->id)
            ->orderBy('name')
            ->get(['id','code','name']);

        return response()->json([
            'parent'   => ['id'=>$unit->id, 'code'=>$unit->code, 'name'=>$unit->name, 'type_level'=>$unit->type_level, 'scheme'=>$unit->scheme],
            'children' => $children,
        ], 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * GET /geo/resolve?scheme=old|new&code=xxx
     * Trả đủ chuỗi cha–con (để auto-fill nếu biết trước code).
     * - old: province/district/ward
     * - new: province/ward
     */
    public function resolve(Request $r)
    {
        $r->validate([
            'scheme' => 'required|in:old,new',
            'code'   => 'required|string',
        ]);

        $u = GeoUnit::where('scheme', $r->scheme)->where('code', $r->code)->first();
        if (!$u) return response()->json(['message' => 'Not found'], 404);

        if ($r->scheme === 'old') {
            $prov=null; $dist=null; $ward=null; $hamlet=null;
            if ($u->type_level == 4) {
                $hamlet = $u;
                $ward   = GeoUnit::find($u->parent_id);
                $dist   = $ward ? GeoUnit::find($ward->parent_id) : null;
                $prov   = $dist ? GeoUnit::find($dist->parent_id) : null;
            } elseif ($u->type_level == 3) {
                $ward = $u;
                $dist = GeoUnit::find($u->parent_id);
                $prov = $dist ? GeoUnit::find($dist->parent_id) : null;
            } elseif ($u->type_level == 2) {
                $dist = $u; $prov = GeoUnit::find($u->parent_id);
            } elseif ($u->type_level == 1) {
                $prov = $u;
            }
            return [
                'scheme'   => 'old',
                'province' => $prov? $prov->only(['id','code','name']) : null,
                'district' => $dist? $dist->only(['id','code','name']) : null,
                'ward'     => $ward? $ward->only(['id','code','name']) : null,
                'hamlet'   => $hamlet? $hamlet->only(['id','code','name']) : null,
            ];
        }

        // NEW: Tỉnh (1) -> Xã (3) -> Thôn (4)
        $prov=null; $ward=null; $hamlet=null;
        if ($u->type_level == 4) {
            $hamlet = $u;
            $ward   = GeoUnit::find($u->parent_id);
            $prov   = $ward ? GeoUnit::find($ward->parent_id) : null;
        } elseif ($u->type_level == 3) {
            $ward = $u;
            $prov = GeoUnit::find($u->parent_id);
        } elseif ($u->type_level == 1) {
            $prov = $u;
        }
        return [
            'scheme'   => 'new',
            'province' => $prov? $prov->only(['id','code','name']) : null,
            'ward'     => $ward? $ward->only(['id','code','name']) : null,
            'hamlet'   => $hamlet? $hamlet->only(['id','code','name']) : null,
        ];
    }
}
