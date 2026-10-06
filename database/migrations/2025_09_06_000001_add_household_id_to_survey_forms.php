<?php
// database/migrations/2025_09_06_000001_add_household_id_to_survey_forms.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('survey_forms', function (Blueprint $t) {
            $t->foreignId('household_id')
              ->nullable()
              ->after('so_phieu')
              ->constrained('households')
              ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('survey_forms', function (Blueprint $t) {
            $t->dropConstrainedForeignId('household_id');
        });
    }
};
