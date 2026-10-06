<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('person_educations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('person_id')
                  ->constrained('people')
                  ->cascadeOnDelete();

            // Khối & lớp chuẩn hoá (KHÔNG thêm ->index() sau constrained)
            $table->foreignId('education_block_id')->nullable()
                  ->constrained('education_blocks')
                  ->nullOnDelete();

            $table->foreignId('education_grade_id')->nullable()
                  ->constrained('education_grades')
                  ->nullOnDelete();

            // Nhãn lớp
            $table->string('class_label', 50)->nullable()->index();

            // Cờ đặc biệt
            $table->boolean('trai_tuyen')->default(false)->index();
            $table->boolean('hoc_hai_buoi')->default(false)->index();
            $table->boolean('hoc_9_10_buoi_tuan')->default(false)->index();
            $table->boolean('is_repeat_grade')->default(false)->index();

            // Địa bàn & trường
            $table->unsignedBigInteger('studying_district_id')->nullable()->index();
            $table->foreignId('school_id')->nullable()
                  ->constrained('schools')
                  ->nullOnDelete();

            // Tốt nghiệp / bổ túc
            $table->foreignId('graduation_level_id')->nullable()
                  ->constrained('graduation_levels')
                  ->nullOnDelete();

            $table->boolean('is_bo_tuc')->default(false)->index();
            $table->string('graduation_year', 9)->nullable();

            // Trình độ nghề
            $table->foreignId('vocational_level_id')->nullable()
                  ->constrained('vocational_levels')
                  ->nullOnDelete();

            $table->string('vocational_grad_year', 9)->nullable();
            $table->string('graduation_certificate_no', 100)->nullable();

            // Học xong / Bỏ học
            $table->unsignedTinyInteger('hoc_xong_lop')->nullable();
            $table->string('hoc_xong_nam', 9)->nullable();
            $table->unsignedTinyInteger('bo_hoc_lop')->nullable();
            $table->string('bo_hoc_nam', 9)->nullable();

            // Niên học hiện hành
            $table->string('academic_year', 9)->nullable();

            $table->text('note')->nullable();
            $table->timestamps();

            // Tối ưu tra cứu tổng hợp
            $table->index(['person_id','academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_educations');
    }
};
