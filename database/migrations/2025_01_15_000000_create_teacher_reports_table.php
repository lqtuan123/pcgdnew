<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_reports', function (Blueprint $table) {
            $table->id();
            
            // Thông tin cơ bản
            $table->string('academic_year', 9); // VD: 2024-2025
            $table->foreignId('ward_id')->constrained('geo_units')->cascadeOnDelete();
            
            // === CẤU TRÚC THEO PREVIEW (32 cột) ===
            
            // 1. Đơn vị (3 cột)
            $table->string('school_name'); // Tên trường
            $table->string('empty_field')->default('-'); // Cột trống
            $table->unsignedTinyInteger('school_rank')->default(0); // Hạng trường
            $table->unsignedTinyInteger('sessions_per_day')->default(0); // 2 buổi/ngày
            
            // 2. CBQL (2 cột)
            $table->unsignedTinyInteger('principal')->default(0); // HT
            $table->unsignedTinyInteger('vice_principal')->default(0); // Phó HT
            
            // 3. Giáo viên - Tổng quát (6 cột)
            $table->unsignedSmallInteger('total_teachers')->default(0); // Tổng GV
            $table->unsignedSmallInteger('teacher_quota')->default(0); // Biên chế
            $table->unsignedSmallInteger('teacher_contract')->default(0); // Hợp đồng
            $table->unsignedSmallInteger('female_teachers')->default(0); // Nữ
            $table->unsignedSmallInteger('ethnic_teachers')->default(0); // Dân tộc
            $table->decimal('teacher_student_ratio', 5, 2)->default(0); // Tỷ lệ GV/L
            
            // 4. Trình độ đào tạo (5 cột)
            $table->unsignedTinyInteger('training_above_uni')->default(0); // Trên ĐH
            $table->unsignedTinyInteger('training_uni')->default(0); // ĐH
            $table->unsignedTinyInteger('training_college')->default(0); // CĐ
            $table->unsignedTinyInteger('training_ped')->default(0); // THSP
            $table->unsignedTinyInteger('training_below_ped')->default(0); // Dưới THSP
            
            // 5. Loại hình đào tạo (7 cột)
            $table->unsignedTinyInteger('training_primary')->default(0); // Tiểu học
            $table->unsignedTinyInteger('training_an')->default(0); // AN
            $table->unsignedTinyInteger('training_mt')->default(0); // MT
            $table->unsignedTinyInteger('training_td')->default(0); // TD
            $table->unsignedTinyInteger('training_informatics')->default(0); // Tin học
            $table->unsignedTinyInteger('training_foreign_lang')->default(0); // NN
            $table->unsignedTinyInteger('training_other')->default(0); // Khác
            
            // 6. Chuẩn nghề nghiệp (4 cột)
            $table->unsignedTinyInteger('standard_good')->default(0); // Tốt
            $table->unsignedTinyInteger('standard_quite')->default(0); // Khá
            $table->unsignedTinyInteger('standard_reach')->default(0); // Đạt
            $table->unsignedTinyInteger('standard_not_reach')->default(0); // Chưa đạt
            
            // 7. TPT Đội (1 cột)
            $table->unsignedTinyInteger('tpt_team')->default(0); // TPT Đội
            
            // 8. Nhân viên (2 cột)
            $table->unsignedTinyInteger('office_staff')->default(0); // Văn phòng
            $table->unsignedTinyInteger('library_other_staff')->default(0); // T.Viện-TBDH
            
            $table->timestamps();
            
            // Indexes
            $table->index(['academic_year', 'ward_id']);
            $table->index(['academic_year', 'school_name']);
            $table->unique(['academic_year', 'ward_id', 'school_name'], 'unique_school_report');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_reports');
    }
};
