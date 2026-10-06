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
        Schema::table('teacher_reports', function (Blueprint $table) {
            // Thêm cột school_id
            $table->unsignedBigInteger('school_id')->nullable()->after('ward_id');
            
            // Thêm foreign key constraints (chỉ thêm school_id vì ward_id đã có)
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            
            // Thêm index để tối ưu hóa truy vấn (chỉ thêm những index chưa có)
            $table->index(['academic_year', 'school_id']);
            $table->index(['ward_id', 'school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_reports', function (Blueprint $table) {
            // Xóa foreign key constraints
            $table->dropForeign(['school_id']);
            
            // Xóa index
            $table->dropIndex(['academic_year', 'school_id']);
            $table->dropIndex(['ward_id', 'school_id']);
            
            // Xóa cột school_id
            $table->dropColumn('school_id');
        });
    }
};
