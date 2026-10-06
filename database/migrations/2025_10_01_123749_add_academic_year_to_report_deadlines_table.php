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
        Schema::table('report_deadlines', function (Blueprint $table) {
            $table->string('academic_year')->nullable()->after('deadline')->comment('Năm học');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_deadlines', function (Blueprint $table) {
            $table->dropColumn('academic_year');
        });
    }
};
