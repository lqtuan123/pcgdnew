<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graduation_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedTinyInteger('agemin')->nullable()->comment('Tuổi tối thiểu');
            $table->unsignedTinyInteger('agemax')->nullable()->comment('Tuổi tối đa');
            $table->timestamps();
        });

        // Ràng buộc: agemin <= agemax (MySQL 8+)
        DB::statement("
            ALTER TABLE graduation_levels
            ADD CONSTRAINT chk_graduation_levels_age_range
            CHECK (
                agemin IS NULL OR agemax IS NULL OR agemin <= agemax
            )
        ");
    }

    public function down(): void
    {
        // Xóa CHECK trước (MySQL 8+)
        DB::statement("ALTER TABLE graduation_levels DROP CHECK chk_graduation_levels_age_range");

        Schema::dropIfExists('graduation_levels');
    }
};
