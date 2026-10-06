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
        Schema::table('elementary_infrastructure_reports', function (Blueprint $table) {
            // Xóa foreign key constraint trước
            $table->dropForeign(['school_id']);
            
            // Thay đổi cột school_id thành nullable
            $table->unsignedBigInteger('school_id')->nullable()->change();
            
            // Thêm lại foreign key constraint
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('elementary_infrastructure_reports', function (Blueprint $table) {
            // Xóa foreign key constraint
            $table->dropForeign(['school_id']);
            
            // Thay đổi cột school_id thành not nullable
            $table->unsignedBigInteger('school_id')->nullable(false)->change();
            
            // Thêm lại foreign key constraint
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });
    }
};