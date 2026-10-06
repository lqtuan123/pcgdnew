<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SuperadminUserSeeder::class, // Tạo superadmin user sau khi tạo roles
            FunctionCategorySeeder::class,
            EthnicityPermissionSeeder::class,
            ReligionPermissionSeeder::class,
            UserManagementPermissionSeeder::class,
            SchoolManagementPermissionSeeder::class,
            TestUserSeeder::class,
            GeoTypeSeeder::class,
            GeoUnitSeeder::class,
            GeoUnitMappingSeeder::class,
            EthnicitySeeder::class,
            DecimalFormatSeeder::class,
            IssuerSeeder::class,
            ReligionSeeder::class,
            LogoSeeder::class,
            ContractTypeSeeder::class,
            ResidenceStatusSeeder::class,
            ResidenceTypeSeeder::class,
            HouseRelationshipSeeder::class,
            DisabilityCategorySeeder::class,
            GraduationLevelSeeder::class,
            EducationLevelSeeder::class,
            EducationGradeSeeder::class,
            EducationBlockSeeder::class,
            VocationalLevelSeeder::class,
            SpecialCircumstanceSeeder::class,
            PriorityCategorySeeder::class,
            SuperadminPermissionSeeder::class,
            ReportDataSeeder::class,
            ReportConfigPermissionSeeder::class,
            StatisticalFormulaPermissionSeeder::class,
            SurveyPermissionSeeder::class,
            EducationPermissionSeeder::class,
            AutoPermissionSeeder::class,
            StatisticalFormulaSeeder::class,
            // Tài khoản phân quyền theo địa giới
            DakLakProvinceUserSeeder::class,
            EaKarWardUserSeeder::class,
            // EaKarSchoolUserSeeder::class, // NOTE: import ds trường trước khi chạy để tạo tk demo.
            // Thôn xóm và permissions
            EaKarVillageSeeder::class,
            ReportPermissionSeeder::class,
            GuideSeeder::class,
        ]);

        // \App\Models\User::factory(10)->create();
    }
}
