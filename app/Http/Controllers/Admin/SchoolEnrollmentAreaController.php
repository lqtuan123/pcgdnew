<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolEnrollmentArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolEnrollmentAreaController extends Controller
{
    // GET /admin/school-areas/{school} - HTML view
    // GET /admin/schools/{school}/areas?year=2025 - JSON API
    public function index(Request $req, School $school)
    {
        $year = (int)($req->get('year') ?? date('Y'));

        $areas = SchoolEnrollmentArea::with(['geoUnit', 'geoUnit.type'])
            ->where('school_id', $school->id)
            ->year($year)
            ->orderBy('id')
            ->get();

        // If it's an API request (AJAX), return JSON
        if ($req->expectsJson() || $req->wantsJson()) {
            return response()->json([
                'school' => $school->only(['id','code','name']),
                'year'   => $year,
                'areas'  => $areas,
            ]);
        }

        // Otherwise return HTML view
        return view('admin.school-areas.index', compact('school', 'areas', 'year'));
    }

    // POST /admin/schools/{school}/areas
    // body: { year: 2025, geo_ids: [123,124,...] }
    public function store(Request $req, School $school)
    {
        $data = $req->validate([
            'year'    => ['required','integer','min:2000','max:2100'],
            'geo_ids' => ['array'],
            'geo_ids.*' => ['integer','exists:geo_units,id'],
        ]);

        $year = (int)$data['year'];
        $ids  = $data['geo_ids'] ?? [];

        DB::transaction(function () use ($school, $year, $ids) {
            // Xoá các area cũ năm này không còn trong danh sách
            SchoolEnrollmentArea::where('school_id',$school->id)->where('year',$year)
                ->whereNotIn('geo_id',$ids)->delete();

            // Upsert các area mới
            $rows = array_map(function($gid) use($school,$year){
                return [
                    'school_id' => $school->id,
                    'geo_id'    => $gid,
                    'year'      => $year,
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ];
            }, $ids);

            if (!empty($rows)) {
                SchoolEnrollmentArea::upsert(
                    $rows,
                    uniqueBy: ['school_id','geo_id','year'],
                    update: ['updated_at']
                );
            }
        });

        return response()->json(['ok'=>true]);
    }

    // DELETE /admin/schools/{school}/areas/{area}
    public function destroy(School $school, SchoolEnrollmentArea $area)
    {
        abort_if($area->school_id !== $school->id, 404);
        $area->delete();
        return response()->json(['ok'=>true]);
    }
}
