<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GeoUnit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GeoFrontController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $userWard = null;
        $userProvince = null;
        
        // Nếu user có ward_id, lấy thông tin ward và province
        if ($user->ward_id) {
            $userWard = GeoUnit::with('parent', 'parent.parent')->find($user->ward_id);
            if ($userWard) {
                // Lấy province từ ward (có thể qua district hoặc trực tiếp)
                $userProvince = ($userWard->parent?->type_level === 1) 
                    ? $userWard->parent 
                    : $userWard->parent?->parent;
            }
        } elseif ($user->province_id) {
            $userProvince = GeoUnit::find($user->province_id);
        }
        
        return view('front.geo.index', compact('user', 'userWard', 'userProvince'));
    }

    public function data(Request $r)
    {
        $r->validate([
            'level'         => 'required|integer|in:1,2,3,4', // 2 chỉ dùng nội bộ nếu bạn còn dữ liệu
            'q'             => 'nullable|string',
            'page'          => 'nullable|integer|min:1',
            'per_page'      => 'nullable|integer|min:5|max:2000',
            'province_code' => 'nullable|string',
            'district_code' => 'nullable|string', // giữ tham số cho tương thích, nhưng không bắt buộc
            'ward_code'     => 'nullable|string',
        ]);

        $level   = (int) $r->level;
        $q       = $r->q;
        $perPage = max(5, min((int)($r->per_page ?? 10), 500)); // chặn trần an toàn
        $page    = (int)($r->page ?? 1);

        // Eager load parent chain để tính tên tỉnh cho C3/C4
        $b = GeoUnit::with(['type','parent','parent.parent','parent.parent.parent'])
            ->where('scheme','new')
            ->where('type_level',$level);

        // ---- Resolve ancestor ids (tỉnh/huyện/xã nếu có) ----
        $provId = null; $distId = null; $wardId = null;

        if ($r->filled('province_code')) {
            $provId = GeoUnit::where('scheme','new')->where('type_level',1)
                ->where('code',$r->province_code)->value('id');
        }
        if ($r->filled('district_code')) {
            $distId = GeoUnit::where('scheme','new')->where('type_level',2)
                ->where('code',$r->district_code)->value('id');
        }
        if ($r->filled('ward_code')) {
            $wardId = GeoUnit::where('scheme','new')->where('type_level',3)
                ->where('code',$r->ward_code)->value('id');
        }

        // ---- Lọc theo tổ tiên (không giả định có cấp quận/huyện) ----
        if ($level === 3) {
            // C3 có thể parent là huyện (nếu tồn tại) HOẶC trực tiếp tỉnh
            if ($distId) {
                $b->where('parent_id',$distId);
            } elseif ($provId) {
                // union: các xã trực thuộc tỉnh + các xã dưới mọi huyện của tỉnh (nếu có)
                $districtIds = GeoUnit::where('scheme','new')->where('type_level',2)
                    ->where('parent_id',$provId)->pluck('id')->all();
                $parentIds = array_merge([$provId], $districtIds);
                $b->whereIn('parent_id', $parentIds);
            }
        }

        if ($level === 4) {
            // C4 parent là C3 (xã). Có thể lọc theo ward_code trực tiếp,
            // hoặc theo district_code / province_code (không cần level 2 tồn tại).
            if ($wardId) {
                $b->where('parent_id',$wardId);
            } elseif ($distId) {
                $wardIds = GeoUnit::where('scheme','new')->where('type_level',3)
                    ->where('parent_id',$distId)->pluck('id');
                $b->whereIn('parent_id',$wardIds);
            } elseif ($provId) {
                // lấy toàn bộ xã dưới tỉnh (trực tiếp hoặc qua huyện), rồi lấy tổ thuộc các xã đó
                $districtIds = GeoUnit::where('scheme','new')->where('type_level',2)
                    ->where('parent_id',$provId)->pluck('id');
                $wardIds = GeoUnit::where('scheme','new')->where('type_level',3)
                    ->where(function($q) use ($provId, $districtIds){
                        $q->where('parent_id', $provId);
                        if ($districtIds->count()) $q->orWhereIn('parent_id', $districtIds);
                    })->pluck('id');
                $b->whereIn('parent_id',$wardIds);
            }
        }

        // C1/C2 không cần đặc biệt, chỉ theo level

        // Search
        if ($q) {
            $b->where(function($w) use ($q){
                $w->where('code','like',"%{$q}%")
                  ->orWhere('name','like',"%{$q}%");
            });
        }

        $p = $b->orderBy('code')->paginate($perPage, ['*'], 'page', $page);

        // map rows + kèm tên tỉnh cho C3/C4
        $rows = collect($p->items())->map(function(GeoUnit $u){
            // xác định tỉnh của $u (đường đi linh hoạt)
            $province = null;
            if ($u->type_level === 1) {
                $province = $u;
            } elseif ($u->type_level === 3) {
                $province = ($u->parent?->type_level === 1)
                    ? $u->parent
                    : $u->parent?->parent;
            } elseif ($u->type_level === 4) {
                $province = ($u->parent?->parent?->type_level === 1)
                    ? $u->parent?->parent
                    : $u->parent?->parent?->parent;
            }

            return [
                'id'           => $u->id,
                'code'         => $u->code,
                'name'         => $u->name,
                'display_name' => $u->display_name,
                'type_level'   => $u->type_level,
                'parent_id'    => $u->parent_id,
                'province'     => $province ? [
                    'id' => $province->id, 'code' => $province->code, 'name' => $province->name
                ] : null,
            ];
        });

        return response()->json([
            'data'         => $rows,
            'total'        => $p->total(),
            'current_page' => $p->currentPage(),
            'last_page'    => $p->lastPage(),
            'per_page'     => $p->perPage(),
        ]);
    }

    /**
     * Tạo thôn/xóm mới (cấp 4)
     */
    public function createHamlet(Request $request)
    {
        // Validate với thông báo tiếng Việt
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:20|unique:geo_units,code',
                'ward_id' => 'required|exists:geo_units,id'
            ], [
                'name.required' => 'Tên thôn/xóm là bắt buộc',
                'name.max' => 'Tên thôn/xóm không được quá 255 ký tự',
                'code.max' => 'Mã thôn/xóm không được quá 20 ký tự',
                'code.unique' => 'Mã thôn/xóm đã tồn tại, vui lòng chọn mã khác',
                'ward_id.required' => 'Vui lòng chọn phường/xã',
                'ward_id.exists' => 'Phường/xã không tồn tại'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Kiểm tra quyền: chỉ user cấp ward mới tạo được thôn/xóm trong phường/xã của mình
        $ward = GeoUnit::findOrFail($request->ward_id);
        
        if (!$this->canCreateHamletInWard($user, $ward)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền tạo thôn/xóm trong phường/xã này'
            ], 403);
        }

        try {
            // Nếu client không gửi mã, tự động sinh mã dựa trên mã của phường/xã
            $code = $request->input('code');
            if (empty($code)) {
                $code = $this->generateHamletCode($ward);
            }

            $hamlet = GeoUnit::create([
                'name' => $request->name,
                'code' => $code,
                'parent_id' => $ward->id,
                'type_level' => 4,
                'scheme' => 'new',
                'geo_type_id' => $this->getHamletGeoTypeId(),
                'slug' => Str::slug($request->name),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã tạo thành công ' . ($this->isThon($hamlet) ? 'thôn' : 'tổ dân phố') . ' ' . $request->name,
                'data' => [
                    'id' => $hamlet->id,
                    'code' => $hamlet->code,
                    'name' => $hamlet->name,
                    'type_level' => $hamlet->type_level,
                    'parent_id' => $hamlet->parent_id,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo thôn/xóm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật thôn/xóm
     */
    public function updateHamlet(Request $request)
    {
        try {
            $request->validate([
                'hamlet_id' => 'required|exists:geo_units,id',
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:20|unique:geo_units,code,' . $request->hamlet_id,
            ], [
                'hamlet_id.required' => 'ID thôn/xóm là bắt buộc',
                'hamlet_id.exists' => 'Thôn/xóm không tồn tại',
                'name.required' => 'Tên thôn/xóm là bắt buộc',
                'name.max' => 'Tên thôn/xóm không được quá 255 ký tự',
                'code.required' => 'Mã thôn/xóm là bắt buộc',
                'code.max' => 'Mã thôn/xóm không được quá 20 ký tự',
                'code.unique' => 'Mã thôn/xóm đã tồn tại, vui lòng chọn mã khác',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        }

        $user = Auth::user();
        $hamlet = GeoUnit::findOrFail($request->hamlet_id);
        
        // Kiểm tra đây có phải là cấp 4 không
        if ($hamlet->type_level != 4) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ được sửa thôn/xóm (cấp 4)'
            ], 400);
        }

        // Kiểm tra quyền sửa (giống quyền tạo)
        if (!$this->canCreateHamletInWard($user, $hamlet->parent)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền sửa thôn/xóm này'
            ], 403);
        }

        try {
            $hamlet->update([
                'name' => $request->name,
                'code' => $request->code,
                'slug' => Str::slug($request->name),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Đã cập nhật thành công ' . ($this->isThon($hamlet) ? 'thôn' : 'tổ dân phố') . ' ' . $request->name,
                'data' => [
                    'id' => $hamlet->id,
                    'code' => $hamlet->code,
                    'name' => $hamlet->name,
                    'type_level' => $hamlet->type_level,
                    'parent_id' => $hamlet->parent_id,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật thôn/xóm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kiểm tra user có thể tạo thôn/xóm trong ward này không
     */
    private function canCreateHamletInWard($user, $ward)
    {
        // Admin có thể tạo ở mọi nơi
        if ($user->isAdmin()) {
            return true;
        }

        // User cấp tỉnh có thể tạo trong tất cả ward thuộc tỉnh của họ
        if ($user->isProvince()) {
            $wardProvinceId = $ward->getProvinceId();
            return $user->province_id == $wardProvinceId;
        }

        // User cấp ward hoặc school chỉ có thể tạo trong ward của chính họ
        if ($user->isWard() || $user->isSchool()) {
            return $user->ward_id == $ward->id;
        }

        return false;
    }

    /**
     * Lấy geo_type_id cho thôn/xóm (cấp 4)
     */
    private function getHamletGeoTypeId()
    {
        // Tìm geo_type có level = 4 và is_default = true
        $geoType = \App\Models\GeoType::where('level', 4)
            ->where('is_default', true)
            ->first();
        
        // Nếu không tìm thấy default, lấy bất kỳ geo_type nào có level = 4
        if (!$geoType) {
            $geoType = \App\Models\GeoType::where('level', 4)->first();
        }
        
        return $geoType ? $geoType->id : null;
    }

    /**
     * Sinh mã cho thôn/xóm tự động dựa trên mã của phường/xã (ward)
     */
    private function generateHamletCode(GeoUnit $ward)
    {
        $base = $ward->code ? (string)$ward->code : ('W' . $ward->id);

        $children = GeoUnit::where('parent_id', $ward->id)
            ->where('code', 'like', $base . '%')
            ->pluck('code')
            ->all();

        $max = 0;
        foreach ($children as $c) {
            $suffix = substr((string)$c, strlen($base));
            if (preg_match('/^0*([0-9]+)$/', $suffix, $m)) {
                $num = (int)$m[1];
                if ($num > $max) $max = $num;
            }
        }

        $next = $max + 1;
        $newCode = $base . str_pad((string)$next, 3, '0', STR_PAD_LEFT);

        // đảm bảo không trùng toàn hệ thống
        while (GeoUnit::where('code', $newCode)->exists()) {
            $next++;
            $newCode = $base . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
        }

        return $newCode;
    }

    /**
     * Xóa thôn/xóm
     */
    public function deleteHamlet(Request $request)
    {
        $request->validate([
            'hamlet_id' => 'required|exists:geo_units,id'
        ]);

        $user = Auth::user();
        $hamlet = GeoUnit::findOrFail($request->hamlet_id);
        
        // Kiểm tra đây có phải là cấp 4 không
        if ($hamlet->type_level != 4) {
            return response()->json([
                'success' => false,
                'message' => 'Chỉ được xóa thôn/xóm (cấp 4)'
            ], 400);
        }

        // Kiểm tra quyền xóa (giống quyền tạo)
        if (!$this->canCreateHamletInWard($user, $hamlet->parent)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa thôn/xóm này'
            ], 403);
        }

        try {
            $hamletName = $hamlet->name;
            $hamlet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa thành công ' . ($this->isThon($hamlet) ? 'thôn' : 'tổ dân phố') . ' ' . $hamletName
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Kiểm tra xem có phải là thôn không (dựa vào tên hoặc logic khác)
     */
    private function isThon($hamlet)
    {
        // Logic đơn giản: nếu tên chứa "thôn" hoặc "buôn" thì là thôn
        $name = strtolower($hamlet->name);
        return str_contains($name, 'thôn') || str_contains($name, 'buôn');
    }
}
