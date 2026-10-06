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
        Schema::create('kindergarten_infrastructure_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('academic_year');
            $table->unsignedBigInteger('ward_id');
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->string('school_name');
            $table->string('school_branch')->nullable(); // Điểm trường
            
            // Tổng số phòng học
            $table->integer('total_classrooms')->default(0);
            
            // Số lớp, nhóm
            $table->integer('total_k5_classes')->default(0); // Tổng số lớp MG 5 tuổi
            $table->integer('mixed_k5_classes')->default(0); // Lớp ghép 5 tuổi
            $table->integer('k5_double_shift_classes')->default(0); // Lớp học 2 buổi/ngày
            $table->integer('under_5_classes')->default(0); // Số lớp, nhóm dưới 5 tuổi
            
            // Phòng học cho lớp MG 5 tuổi
            $table->integer('total_k5_rooms')->default(0); // Tổng số phòng
            $table->decimal('room_class_ratio', 5, 2)->default(0); // Tỷ lệ P/L
            $table->integer('solid_rooms_count')->default(0); // Phòng kiên cố - Tổng số
            $table->decimal('solid_rooms_avg_area', 8, 2)->default(0); // Phòng kiên cố - DTBQ (m2)
            $table->integer('semi_solid_rooms_count')->default(0); // Phòng bán kiên cố - Tổng số
            $table->decimal('semi_solid_rooms_avg_area', 8, 2)->default(0); // Phòng bán kiên cố - DTBQ (m2)
            $table->integer('temporary_rooms_count')->default(0); // Phòng học khác - Tổng số
            $table->decimal('temporary_rooms_avg_area', 8, 2)->default(0); // Phòng học khác - DTBQ (m2)
            $table->integer('fully_equipped_rooms')->default(0); // Đủ bộ TBị, ĐD, ĐC TT
            
            // Phòng vệ sinh
            $table->integer('toilet_rooms_count')->default(0); // Số lượng phòng vệ sinh
            $table->integer('clean_water_system')->default(0); // Số lượng CT nước sạch
            $table->integer('kitchen_count')->default(0); // Số lượng bếp ăn
            
            // Sân và đồ chơi
            $table->integer('playground_count')->default(0); // Số lượng sân chơi
            $table->integer('equipped_playground_count')->default(0); // Số lượng sân có đồ chơi
            
            $table->timestamps();
            
            // Indexes
            $table->index(['academic_year', 'ward_id'], 'kigr_academic_year_ward_id_index');
            $table->index(['academic_year', 'school_name'], 'kigr_academic_year_school_name_index');
            $table->index('ward_id', 'kigr_ward_id_index');
            $table->index('province_id', 'kigr_province_id_index');
            $table->index('district_id', 'kigr_district_id_index');
            $table->index('school_id', 'kigr_school_id_index');
            
            // Foreign keys
            $table->foreign('ward_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('province_id')->references('id')->on('geo_units')->onDelete('set null');
            $table->foreign('district_id')->references('id')->on('geo_units')->onDelete('set null');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kindergarten_infrastructure_reports');
    }
};