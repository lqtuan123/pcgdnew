<?php
/**
 * Cấu hình Backup/Restore DB
 * - 'tables': danh sách bảng BASE TABLE sẽ backup full
 * - 'views' : danh sách VIEW (mặc định KHÔNG export, chỉ để tham chiếu)
 * - 'volatile_tables': nhóm bảng “tạm/log/queue” (chỉ loại trừ khi bạn bật exclude_volatile)
 */
return [

    // Lưu file vào disk nào trong config/filesystems.php
    'storage_disk' => 'local',

    // Giới hạn kích thước mỗi part (MB)
    'max_part_mb' => 5,

    // Khi restore: mặc định drop & create lại schema cho sạch
    'drop_and_create' => true,
    // Nếu bạn không drop, có thể bật truncate để dọn bảng trước khi insert
    'truncate_before_import' => false,

    // Mặc định backup FULL; nếu trong UI bạn tick “Exclude volatile”
    // thì các bảng trong volatile_tables sẽ bị bỏ qua.
    'exclude_volatile' => false,

    // Danh sách bảng (BASE TABLE) theo CSDL hiện tại
    'tables' => [
        'cache',
        'cache_locks',
        'contract_types',
        'decimal_formats',
        'disability_categories',
        'documents',
        'educational_levels',
        'education_blocks',
        'education_grades',
        'elementary_infrastructure_reports',
        'ethnicities',
        'ethnic_minorities',
        'failed_jobs',
        'function_categories',
        'geo_types',
        'geo_units',
        'geo_unit_mappings',
        'graduation_levels',
        'guide_categories',
        'guide_contents',
        'guide_functions',
        'households',
        'household_members',
        'house_relationships',
        'issuers',
        'jobs',
        'job_batches',
        'kindergarten_infrastructure_reports',
        'kindergarten_reports',
        'level_transfer_locks',
        'logos',
        'logo_sizes',
        'migrations',
        'password_reset_tokens',
        'people',
        'person_contacts',
        'person_disabilities',
        'person_educations',
        'person_literacy',
        'person_mamnon',
        'person_special_circumstances',
        'primary_school_infrastructure_reports',
        'priority_categories',
        'religions',
        'report_configs',
        'report_deadlines',
        'report_details',
        'report_groups',
        'report_submissions',
        'residences',
        'residence_statuses',
        'residence_types',
        'role_function_permissions',
        'schools',
        'school_enrollment_areas',
        'school_types',
        'secondary_teacher_reports',
        'sessions',
        'special_circumstances',
        'statistical_formulas',
        'students_off_track',
        'survey_forms',
        'survey_form_details',
        'survey_locks',
        'system_functions',
        'teachers',
        'teacher_reports',
        'users',
        'user_addresses',
        'user_decimal_formats',
        'user_function_permissions',
        'user_roles',
        'vocational_levels',
    ],

    // VIEW (mặc định KHÔNG export; nếu cần, bạn tự thêm chức năng dump định nghĩa VIEW)
    'views' => [
        'v_survey_detail_flat',
    ],

    // Nhóm bảng tạm/log/queue (chỉ loại trừ khi bật exclude_volatile)
    'volatile_tables' => [
        'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
        'sessions', 'password_reset_tokens',
        'survey_locks', 'level_transfer_locks',
    ],
];
