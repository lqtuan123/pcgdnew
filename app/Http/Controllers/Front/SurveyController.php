<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\SurveyForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SurveyController extends Controller
{
    public function index()
    {
        // Kiểm tra quyền xem đối tượng điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'view')) {
            abort(403, 'Bạn không có quyền xem danh sách đối tượng điều tra.');
        }

        return view('front.surveys.index');
    }

    public function data(Request $req)
    {
        // Kiểm tra quyền xem đối tượng điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'view')) {
            return response()->json(['error' => 'Bạn không có quyền xem danh sách đối tượng điều tra.'], 403);
        }

        $perPage = (int)($req->integer('per_page', 50));
        $perPage = $perPage > 0 ? $perPage : 50;

        $sort = $req->get('sort', 'updated_at');
        $dir  = strtolower($req->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Cho phép sort theo các cột an toàn
        $sortable = ['so_phieu','updated_at','detail_count','head_person_name'];
        if (!in_array($sort, $sortable, true)) $sort = 'updated_at';

        $q = SurveyForm::query()
            ->select([
                'survey_forms.*',
                // tên chủ hộ để sort/filter (nếu eager load Person hơi nặng thì có thể join)
                DB::raw("NULL as head_person_name")
            ])
            ->with(['headPerson:id,ho_dem,ten,phone'])
            ->withCount(['details as detail_count' => function($qq){
                $qq->where('is_deleted', false);
            }]);

        // Lọc
        if ($req->filled('so_phieu')) {
            $q->where('so_phieu', 'like', '%'.$req->get('so_phieu').'%');
        }
        if ($req->filled('head_name')) {
            $name = trim($req->get('head_name'));
            $q->whereHas('headPerson', function($qq) use ($name){
                $qq->where('ten','like',"%{$name}%")
                   ->orWhere('ho_dem','like',"%{$name}%")
                   ->orWhereRaw("concat(coalesce(ho_dem,''),' ',ten) like ?", ["%{$name}%"]);
            });
        }
        if ($req->filled('investigator')) {
            $term = '%'.trim($req->get('investigator')).'%';
            // yêu cầu MySQL 8+: JSON_SEARCH
            $q->whereRaw("JSON_SEARCH(investigators, 'one', ?) IS NOT NULL", [$term]);
        }
        if ($req->filled('is_deleted') && in_array($req->get('is_deleted'), ['0','1'], true)) {
            $q->where('is_deleted', (bool)$req->get('is_deleted'));
        }

        // Sort tuỳ cột
        if ($sort === 'head_person_name') {
            // sort theo họ tên chủ hộ (join tạm thời)
            $q->leftJoin('people as hp', 'hp.id', '=', 'survey_forms.head_person_id')
              ->orderByRaw("concat(coalesce(hp.ho_dem,''),' ',hp.ten) {$dir}")
              ->select('survey_forms.*'); // reset select khi join
        } else {
            $q->orderBy($sort, $dir);
        }

        $page = $q->paginate($perPage)->appends($req->query());

        // Map ra JSON như view tiêu thụ
        $page->getCollection()->transform(function ($row) {
            return [
                'id'           => $row->id,
                'so_phieu'     => $row->so_phieu,
                'investigators'=> $row->investigators ?? [],
                'is_deleted'   => (bool)$row->is_deleted,
                'note'         => $row->note ?? null,
                'updated_at'   => optional($row->updated_at)->toIso8601String(),
                'detail_count' => $row->detail_count ?? 0,
                'head_person'  => $row->headPerson ? [
                    'id' => $row->headPerson->id,
                    'full_name' => trim(($row->headPerson->ho_dem ? $row->headPerson->ho_dem.' ' : '').$row->headPerson->ten),
                    'phone' => $row->headPerson->phone,
                ] : null,
            ];
        });

        return response()->json($page);
    }

    // Export – trả file CSV/Excel (tuỳ bạn dùng maatwebsite/excel hay CSV thủ công)
    public function export(Request $req)
    {
        // Kiểm tra quyền xuất đối tượng điều tra
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!$user || !$user->hasPermission('quan_ly_doi_tuong_dieu_tra', 'view')) {
            abort(403, 'Bạn không có quyền xuất danh sách đối tượng điều tra.');
        }

        // TODO: dùng cùng filter như data() rồi build CSV/XLSX
        // Trả tạm 204 để bạn cắm lib export.
        return response()->noContent();
    }
}

