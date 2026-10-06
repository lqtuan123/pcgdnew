<?php

return [
    // 1) Định tuyến report
    'routes' => [
        '1:3'  => \App\Http\Controllers\Front\KindergartenInfrastructureController::class.'@exportExcelForWard',
        '1:4'  => \App\Http\Controllers\Front\KindergartenReportController::class.'@exportExcelForWard',
        '2:12'  => \App\Http\Controllers\ElementaryInfrastructureController::class.'@exportExcelForWard',
        '2:13'  => \App\Http\Controllers\Front\TeacherReportController::class.'@exportExcelForWard',
        '3:23'  => \App\Http\Controllers\Front\PrimarySchoolInfrastructureController::class.'@exportExcelForWard',
        '3:24'  => \App\Http\Controllers\Front\SecondaryTeacherReportController::class.'@exportExcelForWard',
        '3:25'  => \App\Http\Controllers\Front\SecondaryTeacherReportController::class.'@exportExcelForWard35',

        // TiH 6–14 tuổi (group=2: primary, detail=11)
        '2:11' => \App\Reports\TiH614Report::class.'@export',
        '1:17'  => \App\Reports\R11Report::class.'@export',
        '1:7'  => \App\Reports\StudentsByWardReport::class.'@exportExcel',
        '1:8'  => \App\Reports\PreschoolCoverageTrackingReport::class.'@list',
        '1:6'=>\App\Reports\PreschoolDisabilityReport::class.'@export',
          '1:1'=>\App\Reports\R11Report::class.'@export',
        '1:9' => \App\Reports\TKTreCongLapVaNgoaiCongLapReport::class.'@export',
        '1:10' => \App\Reports\R11MN4TuoiReport::class.'@export',
        '1:5' => \App\Reports\GdmnFinanceReport::class.'@export',
        // Danh sách HS theo phường/xã (group=2, detail=7/16/28)
         '2:14'  => \App\Reports\PrimaryOutsideWardReport::class.'@export',
        '2:14'  => \App\Reports\PrimaryOutsideWardReport::class.'@export',
        '2:7'  => \App\Reports\StudentsByWardReport::class.'@exportExcel',
        '2:15' => \App\Reports\PrimaryFromOutsideStudyingHereReport::class.'@export',
        '2:16' => \App\Reports\StudentsByWardReport::class.'@exportExcel',
        '2:17' => \App\Reports\PrimaryDisabilityReport::class.'@export',
        '2:18' => \App\Reports\PrimaryDisabilityListReport::class.'@export',
        '2:19' => \App\Reports\PrimaryNotInSchoolListReport::class.'@export',
        '2:20' => \App\Reports\PrimaryCoverageRegisterReport::class.'@export',
        '3:28' => \App\Reports\StudentsByWardReport::class.'@exportExcel',
        '3:27' => \App\Reports\SecondaryFromOutsideStudyingHereReport ::class.'@export',
        '3:26' => \App\Reports\SecondaryOutsideWardReport::class.'@export',
        
        '3:21' => \App\Reports\R31Report::class.'@export',
        '3:22' => \App\Reports\R32Report::class.'@export',
        '3:30' => \App\Reports\SecondaryCoverageRegisterReport::class.'@export',
        '4:36' => \App\Reports\HighSchoolCoverageRegisterReport::class.'@export',
          '4:35' => \App\Reports\HighSchoolDisabilityReport::class.'@export',
          '4:34' => \App\Reports\SchoolSummaryReport::class.'@export',
        '4:33' => \App\Reports\HighSchoolEffectivenessReport::class.'@export',
          '4:32' => \App\Reports\R42Report::class.'@export', 
             '4:31' => \App\Reports\R41Report::class.'@export', 
            '5:37' => \App\Reports\AntiIlliteracyReport::class.'@export',        
            '5:38' => \App\Reports\LiteracyOutcomeReport::class.'@export',
            '5:39' => \App\Reports\IlliteracyByAgeBandsReport::class.'@export',
            '5:40' => \App\Reports\IlliteratePeopleListReport::class.'@export',
            '5:41' => \App\Reports\AntiIlliteracyLevel1Report::class.'@export',
            '5:42' => \App\Reports\AntiIlliteracyLevel2Report::class.'@export',
            '5:43' => \App\Reports\LiteracyStudentsOutcomeReport::class.'@export',
            '5:44' => \App\Reports\LiteracyStudentsOutcomeLevel2Report::class.'@export',
            '5:45' => \App\Reports\LiteracyOutcomeSummaryReport::class.'@export',
            '5:46' => \App\Reports\EducationLevelStatisticsReport::class.'@export',
            '5:66' => \App\Reports\LiteracyTrackingBookReport::class.'@export',
             
             
          // Group 6: Danh sách học sinh

        '6:54'  => \App\Reports\StudentsStudyingOutsideWardReport::class.'@list',
        '6:55'  => \App\Reports\Students11To14GraduatedPrimaryReport::class.'@list',
        '6:57'  => \App\Reports\TDVHDanTocThieuSoReport::class.'@export',
        '6:58'  => \App\Reports\TDVHDoiTuongNuReport::class.'@export',
        '6:67'  => \App\Reports\PcgdTrackingBookReport::class.'@export',

        '6:47' => \App\Reports\R61Report::class.'@export',
        '6:48' => \App\Reports\R62Report::class.'@export',
        '6:49' => \App\Reports\R63Report::class.'@export',
        '6:50' => \App\Reports\R64Report::class.'@export',

        '6:51' => \App\Reports\R605Report::class.'@export',
        '6:52' => \App\Reports\R606Report::class.'@export',
        '6:53' => \App\Reports\R607Report::class.'@export',
        '6:56' => \App\Reports\R610Report::class.'@export',
        '6:59' => \App\Reports\R613Report::class.'@export',
         '6:65' => \App\Reports\R619Report::class.'@export',
          '6:64' => \App\Reports\R618Report::class.'@export',
          '6:63' => \App\Reports\R617Report::class.'@export',
            '6:62' => \App\Reports\R616Report::class.'@export',
             '6:61' => \App\Reports\R615Report::class.'@export',
    ],

    // 2) Luật validate chung
    'base_rules' => [
        'year'                 => ['required','regex:/^\d{4}-\d{4}$/'],
        'geo.province_id'      => ['required','integer','min:1'],
        'report_group_id'      => ['required','in:1,2,3,4,5,6'],
        'report_detail_id'     => ['required','integer'],
        'education.school_id'  => ['nullable','integer'],
    ],

    // 3) (tuỳ chọn) luật validate riêng cho từng report
    'extra_rules' => [
        // '2:11' => ['geo.ward_id' => ['required','integer','min:1']],
    ],
];
