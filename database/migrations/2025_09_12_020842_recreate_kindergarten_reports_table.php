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
        // Xóa bảng cũ nếu tồn tại
        Schema::dropIfExists('kindergarten_reports');
        
        // Tạo bảng mới với cấu trúc đúng theo view
        Schema::create('kindergarten_reports', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year');
            $table->unsignedBigInteger('ward_id');
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('school_name');
            
            // CBQL, giáo viên, nhân viên (10 cột)
            $table->integer('total_all')->default(0);                    // 1. Tổng số
            $table->integer('contract_work_all')->default(0);            // 2. Hợp đồng làm việc
            $table->integer('contract_labor_all')->default(0);           // 3. Hợp đồng lao động - Tổng số
            $table->integer('cs_policy')->default(0);                    // 4. Hợp đồng lao động - Được hưởng CĐ, CS
            $table->integer('total_cbql')->default(0);                   // 5. CBQL - Tổng số
            $table->integer('vice_principal_cbql')->default(0);          // 6. CBQL - Phó Hiệu trưởng
            $table->integer('total_teachers')->default(0);               // 7. Giáo viên - Tổng số
            $table->integer('ethnic_minority')->default(0);              // 8. Giáo viên - Dân tộc
            $table->decimal('teacher_ratio', 8, 2)->default(0);         // 9. Giáo viên - Tỷ lệ GV/L
            $table->integer('total_staff')->default(0);                  // 10. Nhân viên - Tổng số
            
            // Giáo viên dạy MG 5 tuổi (8 cột)
            $table->integer('total_k5')->default(0);                     // 11. Tổng số
            $table->integer('contract_work_k5')->default(0);             // 12. Hợp đồng làm việc
            $table->integer('contract_labor_k5')->default(0);            // 13. Hợp đồng lao động - Tổng số
            $table->integer('cs_policy_k5')->default(0);                 // 14. Hợp đồng lao động - Được hưởng CĐ, CS
            $table->decimal('teacher_ratio_k5', 8, 2)->default(0);      // 15. Tỷ lệ GV/Lớp
            $table->integer('training_standard')->default(0);            // 16. Trình độ đào tạo - Đạt chuẩn
            $table->integer('training_above_standard')->default(0);      // 17. Trình độ đào tạo - Trên chuẩn
            $table->integer('standard_profession')->default(0);          // 18. Đạt chuẩn nghề nghiệp
            
            $table->timestamps();
            
            // Indexes
            $table->index(['academic_year', 'ward_id']);
            $table->index(['academic_year', 'school_id']);
            $table->index(['ward_id', 'school_id']);
            
            // Foreign keys
            $table->foreign('ward_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kindergarten_reports');
    }
};
