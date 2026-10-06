<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\GeoBrowserController;
use App\Http\Controllers\Admin\GeoController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\SchoolImportController;
use App\Http\Controllers\Admin\UserImportController;
use App\Http\Controllers\Admin\SchoolEnrollmentAreaController;
use App\Http\Controllers\Admin\SchoolTypeController;
use App\Http\Controllers\Admin\EducationalLevelController;
use App\Http\Controllers\Admin\Settings\EthnicityController;
use App\Http\Controllers\Admin\Settings\ReligionController;
use App\Http\Controllers\Admin\Settings\IssuerController;
use App\Http\Controllers\Admin\Settings\DecimalFormatController;
use App\Http\Controllers\Admin\Settings\PriorityCategoryController;
use App\Http\Controllers\Admin\Settings\ResidenceTypeController;
use App\Http\Controllers\Admin\Settings\ResidenceStatusController;
use App\Http\Controllers\Admin\Settings\EducationBlockController;
use App\Http\Controllers\Admin\Settings\GraduationLevelController;
use App\Http\Controllers\Admin\Settings\VocationalLevelController;
use App\Http\Controllers\Admin\Settings\SpecialCircumstanceController;
use App\Http\Controllers\Admin\Settings\LogoController;
use App\Http\Controllers\Admin\Settings\PcgdChiTieuController;
use App\Http\Controllers\Admin\Settings\ChuongTrinhController;
use App\Http\Controllers\Admin\Settings\ChiTieuController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\ReportManagementController;
use App\Http\Controllers\Admin\ReportDetailController; 

// Route dashboard cũ đã được xóa - chỉ sử dụng admin.dashboard
use App\Http\Controllers\Front\HomeController;
// use App\Http\Controllers\Front\NewsController;
// use App\Http\Controllers\Front\UserDirectoryController;
 
use App\Http\Controllers\Front\SurveyController;
use App\Http\Controllers\Front\ResponseController;
use App\Http\Controllers\Front\StatsController;
use App\Http\Controllers\Front\ReportController;
use App\Http\Controllers\Front\GeoFrontController;
use App\Http\Controllers\Front\SchoolController as FrontSchoolController;
use App\Http\Controllers\Front\EthnicityFrontController as FEth;
use App\Http\Controllers\Front\ReligionController as FrontReligion;
use App\Http\Controllers\Front\DocumentController as FrontDocumentController;
use App\Http\Controllers\Front\SurveyIndexController;
use App\Http\Controllers\Front\SurveyExportController;
use App\Http\Controllers\Front\SurveyDetailController;
 use App\Http\Controllers\Front\SurveyFormIndexController;
use App\Http\Controllers\Front\SurveyImportController;
use App\Http\Controllers\Front\SurveyMemberController;
use App\Http\Controllers\Front\TeacherReportController;
use App\Http\Controllers\Front\StudentsOffTrackIndexController;
use App\Http\Controllers\Front\StudentsOffTrackImportOldController;
use App\Http\Controllers\Front\StudentsOffTrackImportNewController;
use App\Http\Controllers\Front\StudentsOffTrackDetailController;
use App\Http\Controllers\Front\PreschoolController;
use App\Http\Controllers\Front\StudentsOffTrackExportController;
// use App\Http\Controllers\Front\SurveyReportsController; // File không tồn tại
use App\Http\Controllers\Front\ReportSearchController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\Front\ReportEntryController;
use Illuminate\Http\Request;
use App\Http\Controllers\Front\ReportManagementFrController as FrontReportManagementController;

use App\Http\Controllers\Front\ReportConfigController;
use App\Http\Controllers\Front\StatisticalFormulaController;

use App\Http\Controllers\Front\GeoApiController;
use App\Http\Controllers\Front\SurveyLockController;
use App\Http\Controllers\GdmnFinanceController;
use App\Http\Controllers\Admin\DbBackupController;
use Illuminate\Support\Facades\Storage;

Route::middleware(['auth']) // thêm permission theo hệ thống của bạn
    ->prefix('locks')->name('front.surveylocks.')
    ->group(function () {
        Route::get('/',        [SurveyLockController::class, 'index'])->name('index');
        Route::post('/',       [SurveyLockController::class, 'store'])->name('store');
        Route::put('/{lock}',  [SurveyLockController::class, 'update'])->name('update');
        Route::delete('/{lock}',[SurveyLockController::class, 'destroy'])->name('destroy');
        Route::put('/survey-locks/{lock}/toggle',[SurveyLockController::class, 'toggle'])->name('toggle');
    });

Route::prefix('ajax/geo')->middleware(['auth'])->name('ajaxgeo.')->group(function () {
    // Lấy danh sách TỈNH theo scheme=old|new (type_level = 1)
    Route::get('/provinces', [GeoApiController::class, 'provinces'])->name('provinces');

    // Lấy CON trực tiếp của 1 đơn vị (quận/huyện của tỉnh; xã của huyện; xã của tỉnh (scheme mới))
    Route::get('/children/{unit}', [GeoApiController::class, 'children'])->name('children');

    // (tuỳ chọn) Trả về đủ chuỗi cha-con theo code + scheme
    Route::get('/resolve', [GeoApiController::class, 'resolve'])->name('resolve'); // ?scheme=old|new&code=xxx
});

Route::prefix('ajax/schools')->middleware(['auth'])->name('ajaxschools.')->group(function () {
    // Lấy danh sách trường học theo ward ID
    Route::get('/by-ward/{wardId}', [\App\Http\Controllers\Admin\SchoolController::class, 'getByWard'])->name('by-ward');
    // Lấy danh sách trường học theo ward code
    Route::get('/by-ward-code/{wardCode}', [\App\Http\Controllers\Admin\SchoolController::class, 'getByWardCode'])->name('by-ward-code');
    // Lấy trường học theo ID (cho trường hợp user bị khóa theo school)
    Route::get('/by-id/{schoolId}', [\App\Http\Controllers\Admin\SchoolController::class, 'getById'])->name('by-id');
    // Lấy trường học theo province ID
    Route::get('/by-province/{provinceId}', [\App\Http\Controllers\Admin\SchoolController::class, 'getByProvince'])->name('by-province');
    // Lấy tất cả trường học (cho trường hợp user chưa chọn ward)
    Route::get('/all', [\App\Http\Controllers\Admin\SchoolController::class, 'getAll'])->name('all');
});

Route::prefix('phieu-dieu-tra')->name('front.surveyforms.')->middleware(['auth'])->group(function () {
    Route::get('/',        [SurveyFormIndexController::class, 'index'])->name('index');
    Route::get('/create',  [SurveyFormIndexController::class, 'create'])->name('create');   // nếu có
    Route::get('/import',  [SurveyImportController::class, 'create'])->name('import');   // nếu có
    Route::get('/export',  [SurveyFormIndexController::class, 'export'])->name('export');   // nếu có
    Route::get('/download-template', [SurveyFormIndexController::class, 'downloadTemplate'])->name('download_template');
    Route::get('/download-import-template', [SurveyImportController::class, 'downloadTemplate'])->name('download_import_template');
    Route::get('/edit/{survey}', [SurveyFormIndexController::class, 'edit'])->name('edit'); // nếu có
    Route::delete('/{survey}',   [SurveyFormIndexController::class, 'destroy'])->name('destroy'); // nếu có
    Route::post('/bulk-delete', [SurveyFormIndexController::class, 'bulkDelete'])->name('bulk_delete'); // ★
 
    Route::get('/createmember',  [SurveyFormIndexController::class, 'createmember'])->name('members.create');   // nếu có
    Route::get('/indexmember',  [SurveyFormIndexController::class, 'indexmember'])->name('members.index');   // nếu có
    Route::get('/split',  [SurveyFormIndexController::class, 'split'])->name('split');   // nếu có

 // ★ NEW: Popup create
    Route::get('/modal/create', [SurveyFormIndexController::class, 'modalCreate'])
        ->name('modal.create');
    Route::post('/', [SurveyFormIndexController::class, 'storeAjax'])
        ->name('store.ajax');
        
    Route::get('/{survey}/modal/edit',            [SurveyFormIndexController::class,'modalEdit'])->name('modal.edit');
    Route::get('/{survey}/modal/split',           [SurveyFormIndexController::class,'modalSplit'])->name('modal.split');
    // actions (AJAX POST)
    Route::put('/{survey}/update',               [SurveyFormIndexController::class,'updateAjax'])->name('update.ajax');
    Route::post('/{survey}/split',                [SurveyFormIndexController::class,'splitAjax'])->name('split.ajax');
    // Route::get('/phieu-dieu-tra/{id}/export', \App\Http\Controllers\Front\SurveyFormExportController::class)
    //  ->name('front.survey-forms.export');

    Route::get('/{survey}/modal/members',         [SurveyFormIndexController::class,'modalMembers'])->name('modal.members.index');
    Route::get('/{survey}/modal/members/create',  [SurveyMemberController::class,'modalCreate'])->name('modal.members.create');
    Route::post('/{survey}/members',              [SurveyMemberController::class,'memberStore'])->name('members.store.ajax');

    Route::get('/modal/members/{detail}/edit', [SurveyMemberController::class,'modalEdit'])->name('modal.members.edit');
    // Route::put('/{survey_id}/members/{detail_id}',            [SurveyMemberController::class,'memberUpdate'])->name('members.update');
    Route::delete('/{survey}/members/{detail}',         [SurveyMemberController::class,'memberDestroy'])->name('members.destroy');
    Route::put('/modal/membersdetails/{detail}',            [SurveyMemberController::class,'memberUpdateDetail'])->name('members.update');

 
    // Modal tách hộ
    Route::get('{survey}/modal/split', [\App\Http\Controllers\Front\SurveySplitController::class, 'modalSplit'])
        ->name('modal.split');

    // Lưu tách hộ
    Route::post('{survey}/split', [\App\Http\Controllers\Front\SurveySplitController::class, 'splitStore'])
        ->name('split.store');

    // API kiểm tra trùng số phiếu (AJAX)
    Route::get('ajax/check-so-phieu', [\App\Http\Controllers\Front\SurveySplitController::class, 'checkSoPhieu'])
        ->name('ajax.check_so_phieu');

    // Modal chuyển hộ
    Route::get('{survey}/modal/move', [\App\Http\Controllers\Front\SurveyMoveHouseholdController::class, 'modal'])
        ->name('modal.move');

    // Lưu chuyển hộ
    Route::post('{survey}/move', [\App\Http\Controllers\Front\SurveyMoveHouseholdController::class, 'store'])
        ->name('move.store');

    // API lấy danh sách thôn/xóm (AJAX)
    Route::get('ajax/villages', [\App\Http\Controllers\Front\SurveyMoveHouseholdController::class, 'ajaxVillages'])
        ->name('move.villages');
 

    // routes/web.php
    Route::get('/{id}/exportpdt', [\App\Http\Controllers\Front\SurveyFormExportController::class, 'byId'])
        ->name('exportpdt');

    Route::get('/so/{so}/exportpdt', [\App\Http\Controllers\Front\SurveyFormExportController::class, 'bySoPhieu'])
        ->name('exportpdt-by-so');
    Route::post('/exportpdt-many', [\App\Http\Controllers\Front\SurveyFormExportController::class, 'exportMany'])
    ->name('exportpdt-many');


    Route::get('{survey}/ajax/merge/search', [\App\Http\Controllers\Front\SurveyFormMergeController::class, 'ajaxSearch'])
        ->name('merge.search'); // ?q=...

    Route::get('{survey}/ajax/merge/members/{other}', [\App\Http\Controllers\Front\SurveyFormMergeController::class, 'ajaxMembers'])
        ->name('merge.members'); // lấy members của phiếu khác

    Route::post('{survey}/merge', [\App\Http\Controllers\Front\SurveyFormMergeController::class, 'store'])
        ->name('merge.store'); // thực hiện ghép
     // Modal hiển thị UI ghép hộ
    Route::get('{survey}/modal/merge', [\App\Http\Controllers\Front\SurveyFormMergeController::class, 'modal'])
        ->name('modal.merge');    
        
});

// báo cáo

Route::prefix('/report')->middleware(['auth'])->name('front.reports.')->group(function () {
    Route::get('/searchreport', [ReportEntryController::class, 'searchreport'])
        ->name('searchreport');
    
    Route::get('/export-1-8', function(Request $req) {
        return app(\App\Reports\PreschoolCoverageTrackingReport::class)->directExport($req);
    })->name('export-1-8');

    // Route::get('/primary-by-ward/export', [SurveyReportsController::class, 'primaryByWardExport'])
    // ->name('primary_by_ward.export');
    
    // So dò thống kê
    Route::get('/so-do-thong-ke', [\App\Http\Controllers\Front\StatisticalVerificationController::class, 'index'])
        ->name('statistical-verification');
    
    Route::post('/so-do-thong-ke/clear-cache', [\App\Http\Controllers\Front\StatisticalVerificationController::class, 'clearCache'])
        ->name('statistical-verification.clear-cache');
    
    Route::get('/so-do-thong-ke/export', function(Request $req) {
        // TODO: Implement export functionality
        return back()->with('info', 'Chức năng xuất Excel đang được phát triển');
    })->name('statistical-verification.export');
    
    // Kiểm tra thống kê
    Route::get('/kiem-tra-thong-ke', [\App\Http\Controllers\Front\StatisticalCheckController::class, 'index'])
        ->name('statistical-check');
    
    Route::post('/kiem-tra-thong-ke/clear-cache', [\App\Http\Controllers\Front\StatisticalCheckController::class, 'clearCache'])
        ->name('statistical-check.clear-cache');
    
    Route::get('/kiem-tra-thong-ke/export', function(Request $req) {
        // TODO: Implement export functionality
        return back()->with('info', 'Chức năng xuất Excel đang được phát triển');
    })->name('statistical-check.export');
});
 
Route::prefix('/doituong-dieutra')->name('front.surveys.')->middleware(['google.auth'])->group(function () {
    Route::get('/', [SurveyIndexController::class, 'index'])->name('index');
    Route::get('/deleted', [SurveyIndexController::class, 'deleted'])->name('deleted');
    Route::get('/create', [SurveyIndexController::class, 'create'])->name('create');
    Route::post('/create', [SurveyIndexController::class, 'store'])->name('store');
    Route::get('/edit/{survey}', [SurveyIndexController::class, 'edit'])->name('edit');
    Route::get('/destroy/{survey}', [SurveyIndexController::class, 'destroy'])->name('destroy');
    Route::post('/{detail}/soft-delete', [SurveyIndexController::class, 'softDelete'])->name('soft-delete');
    Route::post('/{detail}/restore', [SurveyIndexController::class, 'restore'])->name('restore');
    Route::post('/{detail}/hard-delete', [SurveyIndexController::class, 'hardDelete'])->name('hard-delete');
    Route::post('/bulk-soft-delete', [SurveyIndexController::class, 'bulkSoftDelete'])->name('bulk_soft_delete');
    Route::post('/bulk-hard-delete', [SurveyIndexController::class, 'bulkHardDelete'])->name('bulk_hard_delete');
        Route::post('/bulk-restore', [SurveyIndexController::class, 'bulkRestore'])->name('bulk_restore');
        Route::post('/bulk-hard-delete-targets', [SurveyIndexController::class, 'bulkHardDeleteTargets'])->name('bulk_hard_delete_targets');
        Route::post('/bulk-restore-surveys', [SurveyIndexController::class, 'bulkRestoreSurveys'])->name('bulk_restore_surveys');
        Route::post('/bulk-hard-delete-surveys', [SurveyIndexController::class, 'bulkHardDeleteSurveys'])->name('bulk_hard_delete_surveys');
        Route::post('/restore-all-targets', [SurveyIndexController::class, 'restoreAllTargets'])->name('restore_all_targets');
        Route::post('/hard-delete-all-targets', [SurveyIndexController::class, 'hardDeleteAllTargets'])->name('hard_delete_all_targets');
        Route::post('/restore-all-surveys', [SurveyIndexController::class, 'restoreAllSurveys'])->name('restore_all_surveys');
        Route::post('/hard-delete-all-surveys', [SurveyIndexController::class, 'hardDeleteAllSurveys'])->name('hard_delete_all_surveys');
    Route::post('/survey/{survey}/restore', [SurveyIndexController::class, 'restoreSurvey'])->name('survey_restore');
    Route::post('/survey/{survey}/hard-delete', [SurveyIndexController::class, 'hardDeleteSurvey'])->name('survey_hard_delete');
    Route::get('/data', [SurveyIndexController::class, 'data'])->name('data');
    Route::get('/import', [SurveyImportController::class, 'create'])->name('import');
    Route::post('/import', [SurveyImportController::class, 'store'])->name('saveimport');
    //Route::get('/export', [SurveyIndexController::class, 'export'])->name('export');
    Route::get('/export', [SurveyExportController::class, 'export'])->name('export');
    Route::get('/{survey}',            [SurveyDetailController::class,'show'])->name('show');
    Route::get('/{survey}/details',    [SurveyDetailController::class,'data'])->name('details.data');
    
    // Cập nhật bậc tốt nghiệp tự động
    Route::get('/graduation-levels/preview', [SurveyIndexController::class, 'previewUpdateGraduationLevels'])->name('graduation_levels.preview');
    Route::post('/graduation-levels/update', [SurveyIndexController::class, 'updateGraduationLevels'])->name('graduation_levels.update');
    
    // Cập nhật học 9-10 buổi/tuần (khối 1, trẻ 6 tuổi)
    Route::post('/nine-ten-sessions/update', [SurveyIndexController::class, 'updateNineTenSessions'])->name('nine_ten_sessions.update');
});

// Routes cho học sinh trái tuyến (sử dụng StudentsOffTrack model mới)
Route::prefix('/hoc-sinh-trai-tuyen')->name('front.students-off-track.')->middleware(['google.auth'])->group(function () {
    Route::get('/', [StudentsOffTrackIndexController::class, 'index'])->name('index');
    Route::post('/', [StudentsOffTrackIndexController::class, 'store'])->name('store');
    Route::get('/export', [StudentsOffTrackExportController::class, 'export'])->name('export');
    Route::get('/export-template', [StudentsOffTrackExportController::class, 'exportTemplate'])->name('export-template');
    Route::get('/import', [StudentsOffTrackImportOldController::class, 'create'])->name('import');
    Route::post('/import/old', [StudentsOffTrackImportOldController::class, 'store'])->name('import.old');
    Route::post('/import/new', [StudentsOffTrackImportNewController::class, 'store'])->name('import.new');
    Route::get('/detail/{id}', [StudentsOffTrackDetailController::class, 'show'])->name('detail');
    Route::get('/edit/{id}', [StudentsOffTrackDetailController::class, 'edit'])->name('edit');
    Route::put('/{id}', [StudentsOffTrackDetailController::class, 'update'])->name('update');
    Route::delete('/{id}', [StudentsOffTrackDetailController::class, 'destroy'])->name('destroy');
    Route::post('/bulk-delete', [StudentsOffTrackIndexController::class, 'bulkDelete'])->name('bulk-delete');
    
    // Routes cho lên lớp học sinh trái tuyến
    Route::prefix('/level-transfer')->name('level-transfer.')->group(function () {
        Route::get('/', [App\Http\Controllers\Front\StudentsOffTrackLevelTransferController::class, 'index'])->name('index');
        Route::post('/execute', [App\Http\Controllers\Front\StudentsOffTrackLevelTransferController::class, 'execute'])->name('execute');
        Route::get('/schools-by-level', [App\Http\Controllers\Front\StudentsOffTrackLevelTransferController::class, 'getSchoolsByLevel'])->name('schools-by-level');
        Route::get('/geo-units', [App\Http\Controllers\Front\StudentsOffTrackLevelTransferController::class, 'getGeoUnits'])->name('geo-units');
        
        // Routes cho khóa/mở khóa
        Route::post('/lock', [App\Http\Controllers\Front\StudentsOffTrackLevelTransferController::class, 'lock'])->name('lock');
        Route::post('/unlock', [App\Http\Controllers\Front\StudentsOffTrackLevelTransferController::class, 'unlock'])->name('unlock');
        Route::get('/lock-status', [App\Http\Controllers\Front\StudentsOffTrackLevelTransferController::class, 'lockStatus'])->name('lock-status');
    });
});

// Routes cho thực hiện lên lớp
Route::prefix('/thuc-hien-len-lop')->name('front.level-transfer.')->middleware(['auth'])->group(function () {
    Route::get('/', [App\Http\Controllers\Front\LevelTransferController::class, 'index'])->name('index');
    Route::post('/execute', [App\Http\Controllers\Front\LevelTransferController::class, 'execute'])->name('execute');
    Route::get('/schools/{level}', [App\Http\Controllers\Front\LevelTransferController::class, 'getSchools'])->name('schools');
    Route::get('/schools-by-level', [App\Http\Controllers\Front\LevelTransferController::class, 'getSchoolsByLevel'])->name('schools-by-level');
    Route::get('/geo-units', [App\Http\Controllers\Front\LevelTransferController::class, 'getGeoUnits'])->name('geo-units');
    Route::get('/graduation-students', [App\Http\Controllers\Front\LevelTransferController::class, 'getGraduationStudents'])->name('graduation-students');
    Route::get('/get-next-level-schools', [App\Http\Controllers\Front\LevelTransferController::class, 'getNextLevelSchools'])->name('get-next-level-schools');
    Route::post('/lock', [App\Http\Controllers\Front\LevelTransferController::class, 'lock'])->name('lock');
    Route::post('/unlock', [App\Http\Controllers\Front\LevelTransferController::class, 'unlock'])->name('unlock');
    Route::get('/lock-status', [App\Http\Controllers\Front\LevelTransferController::class, 'lockStatus'])->name('lock-status');
});

// Routes cho học sinh mầm non
Route::prefix('/hoc-sinh-mam-non')->name('front.preschool-students.')->middleware(['google.auth'])->group(function () {
    Route::get('/', [PreschoolController::class, 'index'])->name('index');
    Route::get('/{id}/edit', [PreschoolController::class, 'edit'])->name('edit');
    Route::put('/{id}', [PreschoolController::class, 'update'])->name('update');
    Route::get('/export', [PreschoolController::class, 'export'])->name('export');
    Route::get('/import', [PreschoolController::class, 'import'])->name('import');
    Route::post('/import', [PreschoolController::class, 'import'])->name('import.store');
    Route::post('/import/save', [PreschoolController::class, 'importSave'])->name('import.save');
    Route::post('/bulk-delete', [PreschoolController::class, 'bulkDelete'])->name('bulk-delete');
    Route::post('/bulk-update-study-info', [PreschoolController::class, 'bulkUpdateStudyInfo'])->name('bulk-update-study-info');
    Route::post('/bulk-update-gdmn', [PreschoolController::class, 'bulkUpdateGDMN'])->name('bulk-update-gdmn');
});

// Routes cho Kiểm tra dữ liệu
Route::prefix('/kiem-tra-du-lieu')->name('front.data-validation.')->middleware(['google.auth'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Front\DataValidationController::class, 'index'])->name('index');
    Route::get('/check', [\App\Http\Controllers\Front\DataValidationController::class, 'check'])->name('check');
});

Route::prefix('/tai-lieu')->middleware(['auth'])->name('front.documents.')->group(function () {
    Route::get('/',        [FrontDocumentController::class, 'index'])->name('index');
    Route::get('/data',    [FrontDocumentController::class, 'data'])->name('data');
    Route::get('/export',  [FrontDocumentController::class, 'export'])->name('export');
});

Route::prefix('ton-giao')->middleware(['auth'])->name('front.religions.')->group(function () {
    Route::get('/',       [FrontReligion::class,'index'])->name('index');
    Route::get('/data',  [FrontReligion::class,'data'])->name('data');
    Route::get('/export',[FrontReligion::class,'export'])->name('export');
});

Route::prefix('dantoc')->middleware(['auth'])->name('front.ethnicities.')->group(function () {
    // Danh mục Dân tộc (frontend)
    Route::get('/',        [FEth::class, 'index'])->name('index');
    Route::get('/data',   [FEth::class, 'data'])->name('data');
    Route::get('/export', [FEth::class, 'export'])->name('export');
});
Route::prefix('truong')->middleware(['auth'])->name('front.schools.')->group(function () {
    // Trang danh mục (frontend)
    Route::get('/',        [FrontSchoolController::class, 'index'])->name('index');
    // API trả JSON cho bảng (phân trang)
    Route::get('/data',    [FrontSchoolController::class, 'data'])->name('data');
        // API trả JSON cho bảng (phân trang)
    Route::get('/datapicker',    [FrontSchoolController::class, 'datapicker'])->name('datapicker');
     Route::get('/schoolpicker',    [FrontSchoolController::class, 'picker'])->name('schoolpicker');
    // Xuất Excel theo các filter hiện tại
    Route::get('/export',  [FrontSchoolController::class, 'export'])->name('export');
    
    // Cập nhật loại hình trường
    Route::post('/{schoolId}/update-conglap', [FrontSchoolController::class, 'updateConglap'])->name('update-conglap');
});

Route::prefix('bao-cao-giao-vien')->middleware(['auth'])->name('front.teacher-reports.')->group(function () {
    // Trang danh sách báo cáo
    Route::get('/',        [TeacherReportController::class, 'index'])->name('index');
    // Xuất Excel theo mẫu
    Route::get('/export-excel', [TeacherReportController::class, 'exportExcel'])->name('export-excel');
    // Nhập dữ liệu từ Excel
    Route::post('/import-excel', [TeacherReportController::class, 'importExcel'])->name('import-excel');
    // Lưu dữ liệu cấp trường
    Route::post('/save-school-data', [TeacherReportController::class, 'saveSchoolData'])->name('save-school-data');
});

Route::prefix('bao-cao-giao-vien-thcs')->middleware(['auth'])->name('front.secondary-teacher-reports.')->group(function () {
    // Trang danh sách báo cáo
    Route::get('/',        [\App\Http\Controllers\Front\SecondaryTeacherReportController::class, 'index'])->name('index');
    // Xuất Excel theo mẫu
    Route::get('/export-excel', [\App\Http\Controllers\Front\SecondaryTeacherReportController::class, 'exportExcel'])->name('export-excel');
    // Nhập dữ liệu từ Excel
    Route::post('/import-excel', [\App\Http\Controllers\Front\SecondaryTeacherReportController::class, 'importExcel'])->name('import-excel');
    // Lưu dữ liệu cấp trường
    Route::post('/save-school-data', [\App\Http\Controllers\Front\SecondaryTeacherReportController::class, 'saveSchoolData'])->name('save-school-data');
});

Route::prefix('bao-cao-giao-vien-mam-non')->middleware(['auth'])->name('front.kindergarten-reports.')->group(function () {
    // Trang danh sách báo cáo
    Route::get('/',        [\App\Http\Controllers\Front\KindergartenReportController::class, 'index'])->name('index');
    // Xuất Excel theo mẫu
    Route::get('/export-excel',  [\App\Http\Controllers\Front\KindergartenReportController::class, 'exportExcel'])->name('export-excel');
    // Nhập từ Excel
    Route::post('/import-excel', [\App\Http\Controllers\Front\KindergartenReportController::class, 'importExcel'])->name('import-excel');
    // Lưu dữ liệu cấp trường
    Route::post('/save-school-data', [\App\Http\Controllers\Front\KindergartenReportController::class, 'saveSchoolData'])->name('save-school-data');
});

// Báo cáo cơ sở vật chất trường mầm non
Route::prefix('bao-cao-co-so-vat-chat-mam-non')->middleware(['auth'])->name('front.kindergarten-infrastructure.')->group(function () {
    Route::get('/',        [\App\Http\Controllers\Front\KindergartenInfrastructureController::class, 'index'])->name('index');
    Route::get('/export-excel', [\App\Http\Controllers\Front\KindergartenInfrastructureController::class, 'exportExcel'])->name('export-excel');
    Route::get('/template', [\App\Http\Controllers\Front\KindergartenInfrastructureController::class, 'template'])->name('template');
    Route::post('/save-school-data', [\App\Http\Controllers\Front\KindergartenInfrastructureController::class, 'saveSchoolData'])->name('save-school-data');
    Route::post('/import-excel', [\App\Http\Controllers\Front\KindergartenInfrastructureController::class, 'importExcel'])->name('import-excel');
});

// Báo cáo cơ sở vật chất trường THCS
Route::prefix('bao-cao-co-so-vat-chat-thcs')->middleware(['auth'])->name('front.primary-school-infrastructure.')->group(function () {
    // Trang danh sách báo cáo
    Route::get('/',        [\App\Http\Controllers\Front\PrimarySchoolInfrastructureController::class, 'index'])->name('index');
    // Xuất Excel theo mẫu
    Route::get('/export-excel', [\App\Http\Controllers\Front\PrimarySchoolInfrastructureController::class, 'exportExcel'])->name('export-excel');
    // Lưu dữ liệu cấp trường
    Route::post('/save-school-data', [\App\Http\Controllers\Front\PrimarySchoolInfrastructureController::class, 'saveSchoolData'])->name('save-school-data');
    // Nhập Excel
    Route::post('/import-excel', [\App\Http\Controllers\Front\PrimarySchoolInfrastructureController::class, 'importExcel'])->name('import-excel');
});

// Elementary Infrastructure Reports Routes (Báo cáo cơ sở vật chất trường tiểu học)
Route::prefix('bao-cao-co-so-vat-chat-tieu-hoc')->name('front.elementary-infrastructure.')->middleware('auth')->group(function () {
    Route::get('/', [App\Http\Controllers\ElementaryInfrastructureController::class, 'index'])->name('index');
    Route::get('/export-excel', [App\Http\Controllers\ElementaryInfrastructureController::class, 'exportExcel'])->name('export-excel');
    Route::post('/save-school-data', [App\Http\Controllers\ElementaryInfrastructureController::class, 'saveSchoolData'])->name('save-school-data');
    Route::post('/import-excel', [App\Http\Controllers\ElementaryInfrastructureController::class, 'importExcel'])->name('import-excel');
});

Route::middleware(['auth'])->name('front.')->group(function () {
    Route::get('/don-vi-hanh-chinh', [GeoFrontController::class,'index'])->name('geo.index');
    Route::get('/don-vi-hanh-chinh/data', [GeoFrontController::class,'data'])->name('geo.data'); // JSON
    Route::post('/don-vi-hanh-chinh/create-hamlet', [GeoFrontController::class,'createHamlet'])->name('geo.create-hamlet'); // Tạo thôn/xóm mới
    Route::put('/don-vi-hanh-chinh/update-hamlet', [GeoFrontController::class,'updateHamlet'])->name('geo.update-hamlet'); // Sửa thôn/xóm
    Route::delete('/don-vi-hanh-chinh/delete-hamlet', [GeoFrontController::class,'deleteHamlet'])->name('geo.delete-hamlet'); // Xóa thôn/xóm
});
// Quản lý người dùng (Front-end) - Đặt ngoài group để tránh xung đột
Route::prefix('user-management')->name('front.user-management.')->middleware(['auth'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Front\UserManagementController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\Front\UserManagementController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\Front\UserManagementController::class, 'store'])->name('store');
    Route::get('/{id}/edit', [\App\Http\Controllers\Front\UserManagementController::class, 'edit'])->name('edit');
    Route::put('/{id}', [\App\Http\Controllers\Front\UserManagementController::class, 'update'])->name('update');
    Route::delete('/{id}', [\App\Http\Controllers\Front\UserManagementController::class, 'destroy'])->name('destroy');
    Route::get('/{id}/permissions', [\App\Http\Controllers\Front\UserManagementController::class, 'permissions'])->name('permissions');
    Route::post('/{id}/permissions', [\App\Http\Controllers\Front\UserManagementController::class, 'savePermissions'])->name('save-permissions');
});

Route::name('front.')->group(function () {
    // Trang chủ + trang tĩnh
    // Route::get('/', [HomeController::class, 'index'])->name('home');
    // Route::get('/gioi-thieu', [HomeController::class, 'about'])->name('about'); // hoặc Route::view(...)

    // Tin tức (list + chi tiết tùy dùng)
    // Route::get('/tin-tuc', [NewsController::class, 'index'])->name('news');
    // Route::get('/tin-tuc/{slug}', [NewsController::class, 'show'])->name('news.show'); // tuỳ chọn


    // Danh mục
    Route::prefix('danh-muc')->group(function () {
        // Route::get('/nguoi-dung', [UserDirectoryController::class, 'index'])->name('user-directory.index');
     
    });

    // Điều tra (Survey)
   

    // Nhập liệu (Responses)
    Route::get('/nhap-lieu', [ResponseController::class, 'index'])->name('responses.index');

    // Thống kê
    Route::get('/thong-ke/hs-tot-nghiep-nam-qua', [StatsController::class, 'graduates'])
        ->middleware('auth')
        ->name('stats.graduates');
    Route::get('/thong-ke/hs-tot-nghiep-nam-qua/export', [StatsController::class, 'exportGraduates'])
        ->middleware('auth')
        ->name('stats.graduates.export');
    Route::get('/thong-ke/doi-tuong-15-18-tot-nghiep-thcs', [StatsController::class, 'thcsGraduates'])
        ->middleware('auth')
        ->name('stats.thcs-graduates');
    Route::get('/thong-ke/doi-tuong-15-18-tot-nghiep-thcs/export', [StatsController::class, 'exportThcsGraduates'])
        ->middleware('auth')
        ->name('stats.thcs-graduates.export');


    // Báo cáo
    Route::prefix('bao-cao')->name('reports.')->group(function () {
        Route::get('/tong-hop', [ReportController::class, 'summary'])->name('summary');
        Route::get('/xuat', [ReportController::class, 'export'])->name('export');
    });

    // Báo cáo kết quả PCGD
    Route::prefix('bao-cao-ket-qua-pcgd')->name('pcgd-report.')->middleware(['google.auth'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Front\PcgdReportController::class, 'index'])->name('index');
        Route::post('/xuat', [\App\Http\Controllers\Front\PcgdReportController::class, 'export'])->name('export');
        Route::post('/luu-nhap', [\App\Http\Controllers\Front\PcgdReportController::class, 'saveDraft'])->name('save-draft');
        Route::get('/download/{filename}', [\App\Http\Controllers\Front\PcgdReportController::class, 'download'])->name('download');
    });

    // Biên bản kiểm tra kết quả PCGD-XMC
    Route::prefix('bien-ban-kiem-tra-pcgd-xmc')->name('pcgd-xmc-report.')->middleware(['google.auth'])->group(function () {
        Route::get('/', [\App\Http\Controllers\Front\PcgdXmcReportController::class, 'index'])->name('index');
        Route::post('/xuat', [\App\Http\Controllers\Front\PcgdXmcReportController::class, 'export'])->name('export');
        Route::post('/luu-nhap', [\App\Http\Controllers\Front\PcgdXmcReportController::class, 'saveDraft'])->name('save-draft');
        Route::get('/download/{filename}', [\App\Http\Controllers\Front\PcgdXmcReportController::class, 'download'])->name('download');
    });

    // Tra cứu thống kê
    Route::prefix('tra-cuu-thong-ke')->name('reports.search.')->middleware(['google.auth'])->group(function () {
        Route::get('/', [ReportSearchController::class, 'index'])->name('index');
        
        Route::get('/api/report-details', [ReportSearchController::class, 'getReportDetails'])->name('api.report-details');
        Route::get('/quan-ly', [FrontReportManagementController::class, 'index'])->name('management.index');

        // Submissions (requires auth)
        Route::middleware('auth')->group(function () {
            Route::post('/quan-ly/submissions', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'store'])
                ->name('management.submissions.store');
            Route::post('/quan-ly/submissions/bulk', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'bulkStore'])
                ->name('management.submissions.bulk-store');
            Route::post('/quan-ly/submissions/multi', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'multiStore'])
                ->name('management.submissions.multi-store');
            Route::post('/quan-ly/submissions/{submission}/status', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'updateStatus'])
                ->name('management.submissions.update-status');
            Route::post('/quan-ly/submissions/{submission}/forward', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'forward'])
                ->name('management.submissions.forward');
            Route::post('/quan-ly/submissions/{submission}/recall', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'recall'])
                ->name('management.submissions.recall');
            Route::post('/quan-ly/submissions/{submission}/update-ward', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'wardEdit'])
                ->name('management.submissions.update-ward');
            Route::post('/quan-ly/submissions/{submission}/edit', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'edit'])
                ->name('management.submissions.edit');
            Route::delete('/quan-ly/submissions/{submission}', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'destroy'])
                ->name('management.submissions.destroy');
            Route::get('/quan-ly/submissions/{submission}/view-feedback', [\App\Http\Controllers\Front\ReportSubmissionController::class, 'viewFeedback'])
                ->name('management.submissions.view-feedback');

            // Deadlines
            Route::post('/quan-ly/deadlines/ward', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'setWardDeadline'])
                ->name('management.deadlines.ward');
            Route::post('/quan-ly/deadlines/school', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'setSchoolDeadline'])
                ->name('management.deadlines.school');
            Route::post('/quan-ly/deadlines/{deadline}/update', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'updateDeadline'])
                ->name('management.deadlines.update');
            Route::post('/quan-ly/deadlines/{deadline}/deactivate', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'deactivateDeadline'])
                ->name('management.deadlines.deactivate');
            Route::post('/quan-ly/deadlines/{deadline}/activate', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'activateDeadline'])
                ->name('management.deadlines.activate');
            Route::delete('/quan-ly/deadlines/{deadline}', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'destroyDeadline'])
                ->name('management.deadlines.destroy');
            Route::post('/quan-ly/deadlines/bulk', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'bulkDeadlinesAction'])
                ->name('management.deadlines.bulk');
            
            // School Deadline Management Routes
            Route::post('/quan-ly/deadlines/school/{deadline}/activate', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'activateSchoolDeadline'])
                ->name('management.deadlines.school.activate');
            Route::post('/quan-ly/deadlines/school/{deadline}/deactivate', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'deactivateSchoolDeadline'])
                ->name('management.deadlines.school.deactivate');
            Route::delete('/quan-ly/deadlines/school/{deadline}', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'deleteSchoolDeadline'])
                ->name('management.deadlines.school.delete');
            Route::post('/quan-ly/deadlines/school/bulk', [\App\Http\Controllers\Front\ReportManagementFrController::class, 'bulkSchoolDeadlineAction'])
                ->name('management.deadlines.school.bulk');
        });
    });

});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Báo cáo tài chính GDMN - chỉ cho tài khoản cấp xã
    Route::middleware('geo.permission')->group(function () {
        Route::resource('gdmn-finance', GdmnFinanceController::class);
        Route::get('gdmn-finance-export', [GdmnFinanceController::class, 'export'])->name('gdmn-finance.export');
        Route::get('gdmn-finance-template', [GdmnFinanceController::class, 'exportTemplate'])->name('gdmn-finance.template');
        Route::get('gdmn-finance-import-form', [GdmnFinanceController::class, 'importForm'])->name('gdmn-finance.import-form');
        Route::post('gdmn-finance-import', [GdmnFinanceController::class, 'import'])->name('gdmn-finance.import');
    });
});
// Trang chủ - yêu cầu đăng nhập Google
Route::get('/', function () {
    if (Auth::check()) {
        return view('front.home');
    }
    return redirect()->route('auth.google.login');
})->name('front.home');

// Front site - yêu cầu đăng nhập Google (sử dụng middleware)
Route::view('/home', 'front.home')->middleware('google.auth')->name('front.home.alt');

// (tùy chọn) vài trang rỗng để bấm menu không lỗi
Route::view('/gioi-thieu', 'front.blank')->name('front.about');

// Auth routes
require __DIR__.'/auth.php';

// Permission routes
require __DIR__.'/permissions.php';

// Google OAuth routes
Route::get('/auth/google', [UserController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [UserController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/auth/google/login', [UserController::class, 'showGoogleLoginForm'])->name('auth.google.login');
Route::post('/auth/login', [UserController::class, 'loginSubmit'])->name('auth.login.submit');
Route::get('/login', [UserController::class, 'showGoogleLoginForm'])->name('login');

// Route [login] not defined.
// tạm thời thêm các route backup/restore vào web.php
Route::post('/backup/restore/session', [DbBackupController::class, 'createSession'])->name('backup.restore.session');
// Admin login routes
Route::get('/admin/login', [UserController::class, 'showAdminLoginForm'])->name('admin.login');
Route::post('/admin/login', [UserController::class, 'loginAdmin'])->name('admin.login.submit');

// User management routes - sử dụng route có sẵn từ auth.php
Route::get('/user/profile', [UserController::class, 'profile'])->middleware('auth')->name('user.profile');
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {


    Route::get('/backup/progress/{backupId}', function ($backupId) {
    $disk = Storage::disk('local');
    $path = "backups/$backupId/progress.json";
    return $disk->exists($path)
        ? response()->json(json_decode($disk->get($path), true))
        : response()->json(['status'=>'unknown'], 404);
})->name('backup.progress');


  Route::post('/export', [\App\Http\Controllers\Admin\DbBackupController::class,'startExport'])->name('backup.export.start');

        // Liệt kê & tải file (theo thứ tự trong manifest)
        Route::get('backup/list/{backupId}', [\App\Http\Controllers\Admin\DbBackupController::class,'listFiles'])->name('backup.list');
        Route::get('/download/{backupId}/{path}', [\App\Http\Controllers\Admin\DbBackupController::class,'download'])
            ->where('path','.*')->name('backup.download');
        Route::post('/upload-zip', [\App\Http\Controllers\Admin\DbBackupController::class,'uploadZipToBackups'])->name('backup.uploadZip');
        Route::get('/bundle/{backupId}', [\App\Http\Controllers\Admin\DbBackupController::class,'bundle'])->name('backup.bundle');
        // MỚI: khôi phục trực tiếp từ backups/{backupId}
        Route::post('/restore-from/{backupId}', [\App\Http\Controllers\Admin\DbBackupController::class,'restoreFromBackupId'])->name('backup.restoreFrom');
        // RESTORE tuần tự (upload từng file ≤5MB rồi import)
        Route::post('/restore/session', [\App\Http\Controllers\Admin\DbBackupController::class,'createSession'])->name('restore.session');
        Route::post('/restore/upload/{sessionId}', [\App\Http\Controllers\Admin\DbBackupController::class,'uploadPart'])->name('restore.upload');
        Route::post('/restore/run/{sessionId}', [\App\Http\Controllers\Admin\DbBackupController::class,'runImport'])->name('restore.run');
         
    Route::get('/backups', [\App\Http\Controllers\Admin\DbBackupController::class,'index'])->name('backups.index');

    Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Route::view('/products', 'admin.products.index')->name('products.index');
    // UI đăng nhập (mock) - đã chuyển sang route chính
    // Route::view('/login', 'admin.auth.login')->name('login');
    Route::view('/forgot-password', 'admin.auth.forgot')->name('forgot');
    Route::view('/reset-password', 'admin.auth.reset')->name('reset');

    // User management routes
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    
    // User import routes - phải đặt trước users/{user} để tránh xung đột
    Route::get('/users/import', [UserImportController::class, 'create'])->name('users.import.create');
    Route::post('/users/import', [UserImportController::class, 'store'])->name('users.import.store');
    Route::get('/users/import/template', [UserImportController::class, 'downloadTemplate'])->name('users.import.template');
    
    // Bulk operations for users
    Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])->name('users.bulk-delete');
    
    // User detail routes - phải đặt sau các route cụ thể
    Route::get('/users/{user}', [UserController::class, 'showUser'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.update-role');
    Route::patch('/users/{user}/quick-update-role', [UserController::class, 'quickUpdateRole'])->name('users.quick-update-role');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Role management routes
    Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
    Route::get('/roles/{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'getPermissions'])->name('roles.permissions');
    Route::post('/roles/{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
    Route::delete('/roles/bulk-delete', [\App\Http\Controllers\Admin\RoleController::class, 'bulkDelete'])->name('roles.bulk-delete');
    
    // Danh mục tham chiếu routes
    Route::get('/ethnicities', [\App\Http\Controllers\Front\EthnicityFrontController::class, 'index'])->name('ethnicities.index');
    Route::get('/ethnicities/data', [\App\Http\Controllers\Front\EthnicityFrontController::class, 'data'])->name('ethnicities.data');
    Route::get('/ethnicities/export', [\App\Http\Controllers\Front\EthnicityFrontController::class, 'export'])->name('ethnicities.export');
    
    Route::get('/religions', [\App\Http\Controllers\Front\ReligionController::class, 'index'])->name('religions.index');
    Route::get('/religions/data', [\App\Http\Controllers\Front\ReligionController::class, 'data'])->name('religions.data');
    Route::get('/religions/export', [\App\Http\Controllers\Front\ReligionController::class, 'export'])->name('religions.export');
        
        //nhom geo dia phan moi cu
        Route::get('/geo/', [GeoBrowserController::class, 'index'])->name('geo.index');
        // JSON APIs dùng fetch()
        Route::get('/geo/provinces', [GeoBrowserController::class, 'provinces'])->name('geo.provinces'); // ?scheme=old|new
        Route::get('/geo/districts', [GeoBrowserController::class, 'districts'])->name('geo.districts'); // ?province=id
        Route::get('/geo/wards', [GeoBrowserController::class, 'wards'])->name('geo.wards'); // ?district=id
        Route::get('/geo/new-wards', [GeoBrowserController::class, 'newWards'])->name('geo.new-wards'); // ?province=id
        Route::get('/geo/children/{unit}', [GeoBrowserController::class, 'children'])->name('geo.children'); // trả con trực tiếp
        // CRUD
        Route::post('/geo/units', [GeoBrowserController::class, 'storeLevel4'])->name('geo.units.store');
        Route::post('/geo/units/child', [GeoBrowserController::class, 'storeChild'])->name('geo.units.store-child'); // 👈 tạo con cho parent (tự xác định cấp)
        Route::patch('/geo/units/{unit}', [GeoBrowserController::class, 'update'])->name('geo.units.update');   // sửa tên/code
        Route::delete('/geo/units/{unit}', [GeoBrowserController::class, 'destroy'])->name('geo.units.destroy'); // xoá
        // routes/web.php
        Route::get('/geo/mappings', [GeoBrowserController::class, 'mappings'])->name('geo.mappings');         // liệt kê
        Route::post('/geo/mappings', [GeoBrowserController::class, 'storeMapping'])->name('geo.mappings.store');    // tạo
        Route::delete('/geo/mappings/{mapping}', [GeoBrowserController::class, 'destroyMapping'])->name('geo.mappings.destroy'); // xoá
         Route::get('geo/map/old-by-new/{new}', [GeoController::class, 'oldByNew'])
        ->name('admin.geo.map.oldByNew');
       
        // Mapping APIs
        Route::get('/map/new-ward', [GeoController::class, 'mapNewWard'])->name('geo.map.new-ward'); // ?code=xxxxx
        Route::get('/map/old-ward', [GeoController::class, 'mapOldWard'])->name('geo.map.old-ward'); // ?code=cu-xxxxx
         Route::get('/geo/resolve', [GeoController::class, 'resolve'])->name('geo.resolve');

       
       
        // school
        Route::resource('schools', SchoolController::class);
        Route::post('schools/bulk-delete', [SchoolController::class, 'bulkDestroy'])->name('schools.bulk-destroy');
        Route::get('schools/by-ward/{wardId}', [SchoolController::class, 'getByWard'])->name('schools.by-ward');

        Route::get('school-areas/{school}', [SchoolEnrollmentAreaController::class,'index'])->name('school-areas.index');
        Route::post('schools/{school}/areas', [SchoolEnrollmentAreaController::class,'store'])->name('schools.areas.store');
        Route::delete('schools/{school}/areas/{area}', [SchoolEnrollmentAreaController::class,'destroy'])->name('schools.areas.destroy');
        Route::get('exportschools', [SchoolController::class, 'export'])
        ->name('schools.export');
        Route::get('/importschools', [SchoolImportController::class, 'create'])->name('schools.import.create');
        Route::post('/importschools', [SchoolImportController::class, 'store'])->name('schools.import.store');

        // Quản lý loại hình trường học
        Route::resource('school-types', SchoolTypeController::class);
        Route::post('school-types/bulk-delete', [SchoolTypeController::class, 'bulkDestroy'])->name('school-types.bulk-destroy');

        // Quản lý bậc học
        Route::resource('educational-levels', EducationalLevelController::class);
        Route::post('educational-levels/bulk-delete', [EducationalLevelController::class, 'bulkDestroy'])->name('educational-levels.bulk-destroy');

        //Document
        Route::resource('documents', DocumentController::class);
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])
            ->name('documents.download');

        // Report Management
        Route::resource('reports', ReportManagementController::class);
        Route::resource('report-details', ReportDetailController::class);
        Route::resource('statistical-formulas', \App\Http\Controllers\Admin\StatisticalFormulaController::class);
       
    });

    Route::prefix('admin/settings')->name('admin.settings.')->middleware('auth')->group(function () {

        Route::resource('ethnicities', EthnicityController::class);
        Route::post('ethnicities/bulk-delete', [EthnicityController::class, 'bulkDestroy'])->name('ethnicities.bulk-destroy');
        Route::resource('religions', ReligionController::class);
        Route::post('religions/bulk-delete', [ReligionController::class, 'bulkDestroy'])->name('religions.bulk-destroy');
        Route::resource('issuers', IssuerController::class);
        
        // Decimal formats routes - chỉ CRUD cơ bản
        
        Route::resource('decimal-formats', DecimalFormatController::class);
        Route::resource('priority-categories', PriorityCategoryController::class);
        Route::resource('residence-types', ResidenceTypeController::class);
        Route::resource('residence-statuses', ResidenceStatusController::class);
        Route::resource('education-blocks', EducationBlockController::class);
        Route::resource('graduation-levels', GraduationLevelController::class);
        Route::resource('vocational-levels', VocationalLevelController::class);
        Route::resource('special-circumstances', SpecialCircumstanceController::class);
        
        // ===== HỆ THỐNG QUẢN LÝ CHƯƠNG TRÌNH - CHỈ TIÊU (ĐƠN GIẢN HÓA) =====
        
        // Quản lý chương trình (PCGD Mức 1/2/3, XMC Mức 1/2, PCGD THCS)
        Route::post('chuong-trinh/bulk-delete', [ChuongTrinhController::class, 'bulkDelete'])
            ->name('chuong-trinh.bulk-delete');
        Route::resource('chuong-trinh', ChuongTrinhController::class);
        Route::post('chuong-trinh/{chuongTrinh}/toggle-active', [ChuongTrinhController::class, 'toggleActive'])
            ->name('chuong-trinh.toggle-active');
        
        // Quản lý chỉ tiêu (Tạo chỉ tiêu + chọn chương trình áp dụng)
        Route::post('chi-tieu/bulk-delete', [ChiTieuController::class, 'bulkDelete'])
            ->name('chi-tieu.bulk-delete');
        Route::resource('chi-tieu', ChiTieuController::class);
        Route::post('chi-tieu/{chiTieu}/toggle-active', [ChiTieuController::class, 'toggleActive'])
            ->name('chi-tieu.toggle-active');
        
        // Nhập giá trị chỉ tiêu (Trang chính)
        Route::get('pcgd-chi-tieu', [PcgdChiTieuController::class, 'index'])
            ->name('pcgd-chi-tieu.index');
        Route::post('pcgd-chi-tieu/update', [PcgdChiTieuController::class, 'update'])
            ->name('pcgd-chi-tieu.update');
        
        // Logo & Favicon management
        Route::resource('logos', \App\Http\Controllers\Admin\Settings\LogoController::class)->except(['edit','update']);
        Route::post('logos/{logo}/activate', [\App\Http\Controllers\Admin\Settings\LogoController::class, 'activate'])->name('logos.activate');
        
        // Guide Management
        Route::resource('guides', \App\Http\Controllers\Admin\GuideController::class);
        Route::resource('guides.functions', \App\Http\Controllers\Admin\GuideFunctionController::class)->parameters([
            'guides' => 'category',
            'functions' => 'function'
        ]);
        Route::resource('guides.functions.contents', \App\Http\Controllers\Admin\GuideContentController::class)->parameters([
            'guides' => 'category',
            'functions' => 'function',
            'contents' => 'content'
        ]);
        
        // Image Upload for CKEditor
        Route::post('image-upload', [\App\Http\Controllers\Admin\ImageUploadController::class, 'upload'])->name('image-upload');
        Route::post('logos/{logo}/sizes/{size}/activate', [\App\Http\Controllers\Admin\Settings\LogoController::class, 'activateSize'])->name('logos.activate-size');
        Route::get('api/logo/active', [\App\Http\Controllers\Admin\Settings\LogoController::class, 'getActiveLogo'])->name('api.logo.active');
        
    });
 

// Frontend Decimal Format Routes
Route::prefix('cau-hinh')->name('front.decimal-format.')->middleware('auth')->group(function () {
            Route::get('/phan-tach-thap-phan', [App\Http\Controllers\Front\DecimalFormatController::class, 'index'])->name('index');
        Route::post('/phan-tach-thap-phan', [App\Http\Controllers\Front\DecimalFormatController::class, 'update'])->name('update');
        Route::get('/phan-tach-thap-phan/current', [App\Http\Controllers\Front\DecimalFormatController::class, 'getCurrentFormat'])->name('current');
        Route::post('/phan-tach-thap-phan/format', [App\Http\Controllers\Front\DecimalFormatController::class, 'formatNumber'])->name('format');
});

// Frontend PCGD Chi Tieu Routes
Route::prefix('cau-hinh')->name('front.pcgd-chi-tieu.')->middleware('auth')->group(function () {
    Route::get('/chi-tieu-pcgd', [App\Http\Controllers\Front\PcgdChiTieuController::class, 'index'])->name('index');
    Route::post('/chi-tieu-pcgd/toggle-difficult-area', [App\Http\Controllers\Front\PcgdChiTieuController::class, 'toggleDifficultArea'])->name('toggle');
});



// Routes cho cấu hình báo cáo
Route::prefix('cau-hinh-bao-cao')->name('front.report-config.')->middleware('auth')->group(function () {
    Route::get('/', [ReportConfigController::class, 'index'])->name('index');
    Route::post('/update', [ReportConfigController::class, 'update'])->name('update');
    Route::post('/bulk-update', [ReportConfigController::class, 'bulkUpdate'])->name('bulk-update');
});

// Routes cho công thức thống kê
Route::prefix('cong-thuc-thong-ke')->name('front.statistical-formula.')->middleware('auth')->group(function () {
    Route::get('/', [StatisticalFormulaController::class, 'index'])->name('index');
    Route::get('/tieu-chi', [StatisticalFormulaController::class, 'criteria'])->name('criteria');
    Route::post('/toggle-criteria/{formulaId}', [StatisticalFormulaController::class, 'toggleCriteria'])->name('toggle-criteria');
    Route::get('/edit/{reportDetailId}', [StatisticalFormulaController::class, 'edit'])->name('edit');
    Route::post('/update/{reportDetailId}', [StatisticalFormulaController::class, 'update'])->name('update');
    Route::post('/add-formula/{reportDetailId}', [StatisticalFormulaController::class, 'addFormula'])->name('add-formula');
    Route::delete('/delete-formula/{formulaId}', [StatisticalFormulaController::class, 'deleteFormula'])->name('delete-formula');

});

// Routes cho hệ thống hướng dẫn
Route::prefix('huong-dan')->name('front.guide.')->group(function () {
    Route::get('/', [GuideController::class, 'index'])->name('index');
    Route::get('/danh-muc/{categoryId}', [GuideController::class, 'category'])->name('category');
    Route::get('/chuc-nang/{functionId}', [GuideController::class, 'function'])->name('function');
    Route::get('/noi-dung/{contentId}', [GuideController::class, 'content'])->name('content');
});

