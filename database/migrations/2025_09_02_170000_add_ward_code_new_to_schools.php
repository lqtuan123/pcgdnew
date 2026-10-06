<?php
// database/migrations/2025_09_02_170000_add_ward_code_new_to_schools.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('ward_code_new', 190)->nullable()->after('ward_code');
            $table->index('ward_code_new');
        });

        // --- Backfill từ CŨ -> MỚI: nếu có ward_code mà chưa có ward_code_new
        try {
            DB::statement("
                UPDATE schools s
                JOIN geo_units w_old ON w_old.code = s.ward_code
                JOIN geo_unit_mappings m ON m.old_unit_id = w_old.id
                JOIN geo_units w_new ON w_new.id = m.new_unit_id
                SET s.ward_code_new = w_new.code
                WHERE s.ward_code IS NOT NULL AND s.ward_code <> ''
                  AND (s.ward_code_new IS NULL OR s.ward_code_new = '')
            ");
        } catch (\Throwable $e) { /* bỏ qua nếu thiếu bảng/khóa */ }

        // --- Backfill từ MỚI -> CŨ: nếu có ward_code_new mà thiếu ward_code/district_code
        try {
            DB::statement("
                UPDATE schools s
                JOIN geo_units w_new ON w_new.code = s.ward_code_new
                JOIN geo_unit_mappings m ON m.new_unit_id = w_new.id
                JOIN geo_units w_old ON w_old.id = m.old_unit_id
                LEFT JOIN geo_units d_old ON d_old.id = w_old.parent_id
                SET s.ward_code = COALESCE(s.ward_code, w_old.code),
                    s.district_code = COALESCE(s.district_code, d_old.code)
                WHERE s.ward_code_new IS NOT NULL AND s.ward_code_new <> ''
                  AND (s.ward_code IS NULL OR s.ward_code = '')
            ");
        } catch (\Throwable $e) { /* bỏ qua nếu thiếu bảng/khóa */ }
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropIndex(['ward_code_new']);
            $table->dropColumn('ward_code_new');
        });
    }
};
