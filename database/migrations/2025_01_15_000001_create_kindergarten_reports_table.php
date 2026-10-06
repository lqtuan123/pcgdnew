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
        Schema::create('kindergarten_reports', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year');
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('district_id');
            $table->unsignedBigInteger('ward_id');
            $table->string('school_name');
            
            // CBQL, giáo viên, nhân viên - Tổng số toàn bộ
            $table->integer('total_all')->default(0);
            $table->integer('contract_work_all')->default(0);
            $table->integer('contract_labor_all')->default(0);
            
            // CBQL
            $table->integer('total_cbql')->default(0);
            $table->integer('cbql_policy')->default(0);
            $table->integer('cs_policy')->default(0);
            
            // Giáo viên
            $table->integer('total_teachers')->default(0);
            $table->integer('vice_principal')->default(0);
            $table->integer('ethnic_minority')->default(0);
            $table->decimal('teacher_ratio', 8, 2)->default(0);
            
            // Nhân viên
            $table->integer('total_staff')->default(0);
            $table->integer('contract_work_staff')->default(0);
            $table->integer('contract_labor_staff')->default(0);
            
            // Giáo viên dạy MG 5 tuổi
            $table->decimal('teacher_ratio_k5', 8, 2)->default(0);
            $table->integer('training_standard')->default(0);
            $table->integer('training_above_standard')->default(0);
            $table->integer('standard_profession')->default(0);
            
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
        Schema::dropIfExists('kindergarten_reports');
    }
};

