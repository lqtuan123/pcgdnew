<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('level_transfer_locks', function (Blueprint $table) {
            $table->enum('lock_type', ['normal', 'trai_tuyen'])->default('normal')->after('academic_year')
                  ->comment('Loại khóa: normal=khóa bình thường, trai_tuyen=khóa học sinh trái tuyến');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('level_transfer_locks', function (Blueprint $table) {
            $table->dropColumn('lock_type');
        });
    }
};
