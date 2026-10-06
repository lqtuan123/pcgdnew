<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSchoolsRequest;
use App\Services\SchoolService;
use App\Services\GeoService;
use Illuminate\Validation\ValidationException;
class SchoolImportController extends Controller
{
    public function __construct(
        protected SchoolService $schools,
        protected GeoService $geo,
    ) {}

    // Trang upload đơn giản
    public function create()
    {
        return view('admin.schools.import');
    }

    // Xử lý import
    public function store(ImportSchoolsRequest $req)
    {
         @set_time_limit(600);          // 10 phút
    @ini_set('memory_limit','512M');
        $path = $req->file('file')->getRealPath();

        $stats = $this->schools->import($path, $this->geo);

        return back()->with('import_stats', $stats);
    }
}
