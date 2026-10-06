<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('survey_forms', function (Blueprint $table) {
            // Thêm cột province_id và ward_id như alias cho new_province_id và new_ward_id
            $table->unsignedBigInteger('province_id')->nullable()->after('new_province_id');
            $table->unsignedBigInteger('ward_id')->nullable()->after('province_id');
            
            // Tạo index
            $table->index('province_id');
            $table->index('ward_id');
        });
        
        // Copy dữ liệu từ new_province_id và new_ward_id
        DB::statement('UPDATE survey_forms SET province_id = new_province_id');
        DB::statement('UPDATE survey_forms SET ward_id = new_ward_id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('survey_forms', function (Blueprint $table) {
            $table->dropIndex(['province_id']);
            $table->dropIndex(['ward_id']);
            $table->dropColumn(['province_id', 'ward_id']);
        });
    }
};