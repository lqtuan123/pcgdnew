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
        Schema::table('kindergarten_infrastructure_reports', function (Blueprint $table) {
            // Thay đổi cột academic_year từ integer sang string
            $table->string('academic_year')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kindergarten_infrastructure_reports', function (Blueprint $table) {
            // Hoàn tác: thay đổi cột academic_year từ string về integer
            $table->integer('academic_year')->change();
        });
    }
};
