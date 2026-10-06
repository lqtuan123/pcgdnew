<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\TeacherReport;
use App\Services\MultiLevelReportService;
use App\Services\GeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherReportControllerRefactored extends BaseMultiLevelReportController
{
    protected $modelClass = TeacherReport::class;
    protected $viewName = 'front.teacher-reports.index';
    protected $routePrefix = 'teacher-reports';

    public function __construct(GeoService $geoService)
    {
        parent::__construct($geoService);
    }

    /**
     * Lấy tên file export
     */
    protected function getExportFilename($year)
    {
        return 'bao_cao_doi_ngu_giao_vien_' . ($year ?: 'all') . '.xlsx';
    }

    /**
     * Lấy prefix cho temp file
     */
    protected function getTempFilePrefix()
    {
        return 'teacher_report_';
    }

    /**
     * Lấy cấu trúc cột theo cấp độ
     */
    protected function columns($level = 'province')
    {
        $baseColumns = [
            // Đơn vị (4 cột)
            'school_name' => 'Tên trường',
            'school_code' => 'Mã trường', 
            'school_rank' => 'Hạng trường',
            'academic_year' => 'Năm học',
            
            // 2 buổi/ngày (1 cột)
            'sessions_per_day' => '2 buổi/ngày',
            
            // CBQL (2 cột)
            'principal' => 'HT',
            'vice_principal' => 'Phó HT',
            
            // Giáo viên - Tổng quát (5 cột)
            'total_teachers' => 'Tổng số',
            'teacher_quota' => 'Biên chế',
            'teacher_contract' => 'Hợp đồng',
            'female_teachers' => 'Nữ',
            'ethnic_teachers' => 'Dân tộc',
            
            // Tỷ lệ GV/L (1 cột)
            'teacher_student_ratio' => 'Tỷ lệ GV/L',
            
            // Trình độ đào tạo (5 cột)
            'training_above_uni' => 'Trên ĐH',
            'training_uni' => 'ĐH',
            'training_college' => 'CĐ',
            'training_ped' => 'THSP',
            'training_below_ped' => 'Dưới THSP',
            
            // Loại hình đào tạo (7 cột)
            'training_primary' => 'Tiểu học',
            'training_an' => 'AN',
            'training_mt' => 'MT',
            'training_td' => 'TD',
            'training_informatics' => 'Tin học',
            'training_foreign_lang' => 'NN',
            'training_other' => 'Khác',
            
            // Chuẩn nghề nghiệp (4 cột)
            'standard_good' => 'Tốt',
            'standard_quite' => 'Khá',
            'standard_reach' => 'Đạt',
            'standard_not_reach' => 'Chưa đạt',
            
            // Nhân viên (3 cột)
            'tpt_team' => 'TPT Đội',
            'office_staff' => 'Văn phòng',
            'library_other_staff' => 'T.Viện - TBDH',
        ];

        // Điều chỉnh cột theo cấp độ
        if ($level === 'province') {
            // Cấp tỉnh: thay "Tên trường" bằng "Tên xã/phường"
            $baseColumns['school_name'] = 'Tên xã/phường';
            // Ẩn cột "Hạng trường"
            unset($baseColumns['school_rank']);
        } elseif ($level === 'ward') {
            // Cấp xã: hiển thị "Tên trường" và "Hạng trường"
            // Không thay đổi gì
        } else {
            // Cấp trường: ẩn "Tên trường" và "Hạng trường"
            unset($baseColumns['school_name']);
            unset($baseColumns['school_rank']);
        }

        return $baseColumns;
    }

    /**
     * Lấy nhóm cột
     */
    protected function columnGroups($level = 'province')
    {
        $groups = [
            'don-vi' => [
                'title' => 'Đơn vị',
                'colspan' => $level === 'province' ? 3 : ($level === 'ward' ? 4 : 2),
                'columns' => $level === 'province' ? 
                    ['school_name', 'school_code', 'academic_year'] : 
                    ($level === 'ward' ? 
                        ['school_name', 'school_code', 'school_rank', 'academic_year'] : 
                        ['school_code', 'academic_year'])
            ],
            'cbql' => [
                'title' => 'CBQL',
                'colspan' => 2,
                'columns' => ['principal', 'vice_principal']
            ],
            'giao-vien' => [
                'title' => 'Giáo viên',
                'colspan' => 6,
                'subgroups' => [
                    'tong-quat' => [
                        'title' => 'Tổng quát',
                        'colspan' => 5,
                        'columns' => ['total_teachers', 'teacher_quota', 'teacher_contract', 'female_teachers', 'ethnic_teachers']
                    ],
                    'ty-le' => [
                        'title' => 'Tỷ lệ GV/L',
                        'colspan' => 1,
                        'columns' => ['teacher_student_ratio']
                    ]
                ]
            ],
            'trinh-do' => [
                'title' => 'Trình độ đào tạo',
                'colspan' => 5,
                'columns' => ['training_above_uni', 'training_uni', 'training_college', 'training_ped', 'training_below_ped']
            ],
            'loai-hinh' => [
                'title' => 'Loại hình đào tạo',
                'colspan' => 7,
                'columns' => ['training_primary', 'training_an', 'training_mt', 'training_td', 'training_informatics', 'training_foreign_lang', 'training_other']
            ],
            'chuan' => [
                'title' => 'Chuẩn nghề nghiệp',
                'colspan' => 4,
                'columns' => ['standard_good', 'standard_quite', 'standard_reach', 'standard_not_reach']
            ],
            'nhan-vien' => [
                'title' => 'Nhân viên',
                'colspan' => 3,
                'columns' => ['tpt_team', 'office_staff', 'library_other_staff']
            ]
        ];

        return $groups;
    }
}
