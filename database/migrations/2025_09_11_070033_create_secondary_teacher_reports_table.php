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
        Schema::create('secondary_teacher_reports', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year');
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('district_id');
            $table->unsignedBigInteger('ward_id');
            $table->string('school_name');
            $table->integer('school_rank');
            
            // CBQL
            $table->integer('principal')->default(0);
            $table->integer('vice_principal')->default(0);
            
            // Giáo viên - Tổng quát
            $table->integer('total_teachers')->default(0);
            $table->integer('staff_quota')->default(0);
            $table->integer('contract_teachers')->default(0);
            $table->integer('female_teachers')->default(0);
            $table->integer('ethnic_teachers')->default(0);
            $table->decimal('teacher_student_ratio', 8, 2)->default(0);
            
            // Trình độ đào tạo
            $table->integer('training_above_uni')->default(0);
            $table->integer('training_uni')->default(0);
            $table->integer('training_college')->default(0);
            $table->integer('training_ped')->default(0);
            
            // Chuyên ngành đào tạo
            $table->integer('spec_math')->default(0);
            $table->integer('spec_literature')->default(0);
            $table->integer('spec_physics')->default(0);
            $table->integer('spec_chemistry')->default(0);
            $table->integer('spec_biology')->default(0);
            $table->integer('spec_history')->default(0);
            $table->integer('spec_geography')->default(0);
            $table->integer('spec_music')->default(0);
            $table->integer('spec_art')->default(0);
            $table->integer('spec_pe')->default(0);
            $table->integer('spec_english')->default(0);
            $table->integer('spec_russian')->default(0);
            $table->integer('spec_french')->default(0);
            $table->integer('spec_other_lang')->default(0);
            $table->integer('spec_civic_edu')->default(0);
            $table->integer('spec_technology')->default(0);
            $table->integer('spec_informatics')->default(0);
            
            // TPT Đội
            $table->integer('tpt_team')->default(0);
            
            // Chuẩn nghề nghiệp
            $table->integer('standard_good')->default(0);
            $table->integer('standard_quite')->default(0);
            $table->integer('standard_reach')->default(0);
            $table->integer('standard_not_reach')->default(0);
            
            // Nhân viên
            $table->integer('library_staff')->default(0);
            $table->integer('lab_staff')->default(0);
            $table->integer('office_staff')->default(0);
            $table->integer('medical_staff')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['academic_year', 'ward_id']);
            $table->index(['academic_year', 'school_name']);
            $table->foreign('province_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('district_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('ward_id')->references('id')->on('geo_units')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secondary_teacher_reports');
    }
};
