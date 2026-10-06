<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeoType;
use App\Models\GeoUnit;
use App\Models\GeoUnitMapping;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GeoBrowserController extends Controller
{

     
    public function mappings(Request $req)
    {
        $level = (int) $req->query('level', 1);
        $oldParent = $req->query('old_parent_id');
        $newParent = $req->query('new_parent_id');

        $q = GeoUnitMapping::query()
        ->join('geo_units as o', 'o.id', '=', 'geo_unit_mappings.old_unit_id')
        ->join('geo_units as n', 'n.id', '=', 'geo_unit_mappings.new_unit_id')
        ->where('o.type_level', $level)
        ->where('n.type_level', $level)
        ->selectRaw('geo_unit_mappings.*, o.id as o_id, o.name as o_name, o.code as o_code, n.id as n_id, n.name as n_name, n.code as n_code');

        // lọc theo parent theo quy ước hiện tại
        if (in_array($level, [3,4])) {
            if ($oldParent) $q->where('o.parent_id', $oldParent);
            if ($newParent) $q->where('n.parent_id', $newParent);
        }

        $rows = $q->orderBy('o.name')->get()->map(function($r){
            return [
                'id' => $r->id,
                'old' => ['id'=>$r->o_id, 'name'=>$r->o_name, 'code'=>$r->o_code],
                'new' => ['id'=>$r->n_id, 'name'=>$r->n_name, 'code'=>$r->n_code],
                'relation' => $r->relation,
                'weight' => $r->weight,
                'effective_date' => optional($r->effective_date)->format('Y-m-d'),
                'note' => $r->note,
            ];
        });

        return response()->json(['ok'=>true,'data'=>$rows]);
    }

    public function storeMapping(Request $req)
    {
        $v = $req->validate([
            'old_unit_id'    => ['required','integer','exists:geo_units,id'],
            'new_unit_id'    => ['required','integer','exists:geo_units,id', 'different:old_unit_id'],
            'relation'       => ['nullable','string','max:32'],
            'weight'         => ['nullable','numeric'],
            'effective_date' => ['nullable','date'],
            'note'           => ['nullable','string','max:500'],
        ]);

        $exists = GeoUnitMapping::where('old_unit_id', $v['old_unit_id'])
                ->where('new_unit_id', $v['new_unit_id'])->exists();
        if ($exists) {
            return response()->json(['ok'=>false,'message'=>'Ánh xạ này đã tồn tại'], 422);
        }

        $m = GeoUnitMapping::create($v);
        return response()->json(['ok'=>true,'id'=>$m->id,'toast'=>'Đã thêm ánh xạ']);
    }

    public function destroyMapping(GeoUnitMapping $mapping)
    {
        $mapping->delete();
        return response()->json(['ok'=>true,'toast'=>'Đã xoá ánh xạ']);
    }

    public function index()
    {
        // Trang Blade — phần dữ liệu sẽ lấy qua fetch(JSON) để nhẹ trang
        return view('admin.geo.index');
    }

    // Lấy danh sách tỉnh theo scheme (old/new)
    public function provinces(Request $request)
    {
        $scheme = $request->query('scheme', 'old');

        $items = GeoUnit::query()
            ->where('scheme', $scheme)
            ->where('type_level', 1)
            ->orderBy('name')
            ->select('id','code','name','type_level','parent_id','scheme')
            ->get();

        return response()->json($items);
    }

    // Lấy con trực tiếp của 1 đơn vị (dùng cho drill-down)
    public function children(GeoUnit $unit)
    {
        $children = GeoUnit::query()
            ->where('parent_id', $unit->id)
            ->orderBy('name')
            ->select('id','code','name','type_level','parent_id','scheme')
            ->get();

        return response()->json([
            'parent'   => $unit->only(['id','code','name','type_level','parent_id','scheme']),
            'children' => $children,
        ]);
    }

    // Lấy danh sách quận/huyện theo tỉnh (old scheme)
    public function districts(Request $request)
    {
        $provinceId = $request->query('province');
        
        if (!$provinceId) {
            return response()->json([]);
        }

        $items = GeoUnit::query()
            ->where('parent_id', $provinceId)
            ->where('type_level', 2)
            ->where('scheme', 'old')
            ->orderBy('name')
            ->select('id','code','name','type_level','parent_id','scheme')
            ->get();

        return response()->json($items);
    }

    // Lấy danh sách xã/phường theo quận/huyện (old scheme)
    public function wards(Request $request)
    {
        $districtId = $request->query('district');
        
        if (!$districtId) {
            return response()->json([]);
        }

        $items = GeoUnit::query()
            ->where('parent_id', $districtId)
            ->where('type_level', 3)
            ->where('scheme', 'old')
            ->orderBy('name')
            ->select('id','code','name','type_level','parent_id','scheme')
            ->get();

        return response()->json($items);
    }

    // Lấy danh sách cấp 3 theo tỉnh (new scheme)
    public function newWards(Request $request)
    {
        $provinceId = $request->query('province');
        
        if (!$provinceId) {
            return response()->json([]);
        }

        $items = GeoUnit::query()
            ->where('parent_id', $provinceId)
            ->where('type_level', 3)
            ->where('scheme', 'new')
            ->orderBy('name')
            ->select('id','code','name','type_level','parent_id','scheme')
            ->get();

        return response()->json($items);
    }

     
   // ✅ Tạo CON dưới 1 parent: tự suy cấp = parent.type_level + 1 (chỉ cho 2..4)
    public function storeChild(Request $request)
    {
        $v = $request->validate([
            'parent_id' => ['required','integer','exists:geo_units,id'],
            'code'      => ['required','string','max:64'],
            'name'      => ['required','string','max:255'],
        ]);
        $parent = GeoUnit::findOrFail($v['parent_id']);
        $level  = (int) $parent->type_level + 1;
        if ($level < 2 || $level > 4) {
            return response()->json(['ok'=>false,'message'=>'Chỉ được thêm tới cấp 4'], 422);
        }
        $geoTypeId = GeoType::where('level', $level)->value('id');
        if (!$geoTypeId) {
            return response()->json(['ok'=>false,'message'=>"Chưa có GeoType level={$level}"], 422);
        }
        // unique code trong 1 scheme
        $exists = GeoUnit::where('scheme', $parent->scheme)->where('code', $v['code'])->exists();
        if ($exists) return response()->json(['ok'=>false,'message'=>'Mã code đã tồn tại trong scheme này'], 422);

        $unit = GeoUnit::create([
            'geo_type_id' => $geoTypeId,
            'type_level'  => $level,
            'parent_id'   => $parent->id,
            'uuid'        => (string) Str::uuid(),
            'code'        => $v['code'],
            'name'        => $v['name'],
            'slug'        => Str::slug($v['name']),
            'scheme'      => $parent->scheme,
            'valid_from'  => null,
            'valid_to'    => null,
            'meta'        => null,
        ]);

        return response()->json(['ok'=>true, 'unit'=>$unit, 'toast'=>"Đã thêm cấp {$level}."]);
    }

    // ✅ Sửa tên và/hoặc code
    public function update(Request $request, GeoUnit $unit)
    {
        $v = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'code' => ['sometimes','string','max:64'],
        ]);

        if (array_key_exists('code', $v)) {
            $dup = GeoUnit::where('scheme', $unit->scheme)
                ->where('code', $v['code'])
                ->where('id', '!=', $unit->id)
                ->exists();
            if ($dup) return response()->json(['ok'=>false,'message'=>'Mã code đã tồn tại trong scheme này'], 422);
            $unit->code = $v['code'];
        }
        if (array_key_exists('name', $v)) {
            $unit->name = $v['name'];
            $unit->slug = Str::slug($v['name']);
        }
        $unit->save();

        return response()->json(['ok'=>true,'unit'=>$unit,'toast'=>'Đã cập nhật.']);
    }

    // ✅ Xoá an toàn (không cho xoá nếu còn con/đang được tham chiếu)
    public function destroy(Request $request, GeoUnit $unit)
    {
        // chặn xoá nếu còn con
        if ($unit->children()->exists()) {
            return response()->json(['ok'=>false,'message'=>'Không thể xoá vì còn đơn vị con'], 422);
        }
        // chặn xoá nếu có mapping
        $mapped = GeoUnitMapping::where('old_unit_id',$unit->id)
                  ->orWhere('new_unit_id',$unit->id)->exists();
        if ($mapped) {
            return response()->json(['ok'=>false,'message'=>'Không thể xoá vì đang ánh xạ mới/cũ'], 422);
        }
        // chặn xoá nếu có UserAddress tham chiếu
        $inAddress = UserAddress::where('geo_unit_id',$unit->id)->exists();
        if ($inAddress) {
            return response()->json(['ok'=>false,'message'=>'Không thể xoá vì đang sử dụng trong địa chỉ người dùng'], 422);
        }

        $unit->delete();
        return response()->json(['ok'=>true,'toast'=>'Đã xoá.']);
    }
    // Thêm cấp 4 (con của cấp 3)
    public function storeLevel4(Request $request)
    {
        $validated = $request->validate([
            'parent_id'  => ['required','integer','exists:geo_units,id'],
            'code'       => ['required','string','max:64'],
            'name'       => ['required','string','max:255'],
        ]);

        $parent = GeoUnit::findOrFail($validated['parent_id']);
        if ((int)$parent->type_level !== 3) {
            return response()->json(['ok'=>false,'message'=>'Chỉ được thêm con dưới cấp 3'], 422);
        }

        // Lấy geo_type_id của level=4
        $geoTypeId = GeoType::where('level', 4)->value('id');
        if (!$geoTypeId) {
            return response()->json(['ok'=>false,'message'=>'Chưa có GeoType level=4. Hãy chạy GeoTypeLevel4Seeder.'], 422);
        }

        // Đảm bảo "code + scheme" không trùng (nếu bạn đang enforce unique ở DB thì sẽ báo lỗi)
        $exists = GeoUnit::where('code', $validated['code'])
            ->where('scheme', $parent->scheme) // đặt cùng scheme với cha
            ->exists();
        if ($exists) {
            return response()->json(['ok'=>false,'message'=>'Mã code đã tồn tại trong scheme này.'], 422);
        }

        $unit = GeoUnit::create([
            'geo_type_id' => $geoTypeId,
            'type_level'  => 4,
            'parent_id'   => $parent->id,
            'uuid'        => (string) Str::uuid(),
            'code'        => $validated['code'],
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'scheme'      => $parent->scheme,       // giữ cùng scheme
            'valid_from'  => null,
            'valid_to'    => null,
            'meta'        => null,
        ]);

        return response()->json([
            'ok'    => true,
            'unit'  => $unit->only(['id','code','name','type_level','parent_id','scheme']),
            'toast' => 'Đã thêm cấp 4.',
        ]);
    }
}
