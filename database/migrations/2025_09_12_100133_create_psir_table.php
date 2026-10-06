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
        Schema::create('primary_school_infrastructure_reports', function (Blueprint $table) {
            $table->id();
            
            // Thông tin cơ bản
            $table->string('academic_year');
            $table->unsignedBigInteger('ward_id');
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('district_id');
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('school_name');
            $table->string('school_branch')->nullable();
            
            // Nhóm Số lớp (cột 4-5)
            $table->integer('total_classes')->default(0);
            $table->integer('combined_classes')->default(0);
            
            // Nhóm Số phòng học (cột 6-10)
            $table->integer('solid_classrooms')->default(0);
            $table->integer('semi_solid_classrooms')->default(0);
            $table->integer('temporary_classrooms')->default(0);
            $table->integer('rented_classrooms')->default(0);
            $table->decimal('classroom_class_ratio', 8, 2)->default(0);
            
            // Nhóm Số phòng chức năng (cột 11-25)
            // Hiệu trưởng (cột 11)
            $table->integer('principal_rooms_count')->default(0);
            
            // Phó Hiệu trưởng (cột 12)
            $table->integer('vice_principal_rooms_count')->default(0);
            
            // Văn phòng (cột 13)
            $table->integer('office_rooms_count')->default(0);
            
            // Y tế (cột 14)
            $table->integer('medical_rooms_count')->default(0);
            
            // Trụ sở HĐ Đội (cột 15)
            $table->integer('youth_union_rooms_count')->default(0);
            
            // Phòng họp (cột 16-17)
            $table->integer('meeting_rooms_count')->default(0);
            $table->decimal('meeting_rooms_area', 10, 2)->default(0);
            
            // Thư viện (cột 18-19)
            $table->integer('library_rooms_count')->default(0);
            $table->decimal('library_rooms_area', 10, 2)->default(0);
            
            // Thiết bị (cột 20-21)
            $table->integer('equipment_rooms_count')->default(0);
            $table->decimal('equipment_rooms_area', 10, 2)->default(0);
            
            // Giáo viên (cột 22-23)
            $table->integer('teacher_rooms_count')->default(0);
            $table->decimal('teacher_rooms_area', 10, 2)->default(0);
            
            // Học sinh (cột 24-25)
            $table->integer('student_rooms_count')->default(0);
            $table->decimal('student_rooms_area', 10, 2)->default(0);
            
            // Nhóm Sân chơi (cột 26-27)
            $table->integer('playground_count')->default(0);
            $table->decimal('playground_area', 10, 2)->default(0);
            
            // Nhóm Bãi tập (cột 28-29)
            $table->integer('training_ground_count')->default(0);
            $table->decimal('training_ground_area', 10, 2)->default(0);
            
            $table->timestamps();
            
            // Indexes với tên ngắn
            $table->index(['academic_year', 'ward_id'], 'psir_ay_ward_idx');
            $table->index(['academic_year', 'province_id'], 'psir_ay_prov_idx');
            $table->index(['academic_year', 'district_id'], 'psir_ay_dist_idx');
            $table->index(['academic_year', 'school_id'], 'psir_ay_school_idx');
            $table->index('school_name', 'psir_school_name_idx');
            
            // Foreign keys
            $table->foreign('ward_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('province_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('district_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('primary_school_infrastructure_reports');
    }
};
