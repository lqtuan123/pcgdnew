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
        Schema::create('report_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên nhóm báo cáo (Mầm non, Tiểu học, etc.)
            $table->string('code')->unique(); // Mã nhóm báo cáo
            $table->text('description')->nullable(); // Mô tả nhóm báo cáo
            $table->integer('sort_order')->default(0); // Thứ tự sắp xếp
            $table->boolean('is_active')->default(true); // Trạng thái hoạt động
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_groups');
    }
};
