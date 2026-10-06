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
        Schema::create('report_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_group_id')->constrained('report_groups')->onDelete('cascade'); // Khóa ngoại đến nhóm báo cáo
            $table->string('name'); // Tên báo cáo chi tiết
            $table->string('code')->unique(); // Mã báo cáo chi tiết
            $table->text('description')->nullable(); // Mô tả báo cáo
            $table->string('value')->nullable(); // Giá trị/dữ liệu báo cáo
            $table->integer('sort_order')->default(0); // Thứ tự sắp xếp trong nhóm
            $table->boolean('is_active')->default(true); // Trạng thái hoạt động
            $table->timestamps();
            
            // Index để tối ưu truy vấn
            $table->index(['report_group_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_details');
    }
};
