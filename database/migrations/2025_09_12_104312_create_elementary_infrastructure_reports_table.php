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
        Schema::create('elementary_infrastructure_reports', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year');
            $table->unsignedBigInteger('ward_id');
            $table->unsignedBigInteger('school_id');
            $table->string('school_name');
            $table->integer('school_rank')->nullable();
            
            // Số đơn vị và lớp
            $table->integer('number_of_schools')->default(0);
            $table->integer('number_of_classes')->default(0);
            
            // Số phòng học
            $table->integer('permanent_classrooms')->default(0);
            $table->integer('semi_permanent_classrooms')->default(0);
            $table->integer('temporary_classrooms')->default(0);
            $table->decimal('room_class_ratio', 5, 2)->default(0);
            
            // Phòng chức năng
            $table->integer('principal_room')->default(0);
            $table->integer('vice_principal_room')->default(0);
            $table->integer('office_room')->default(0);
            $table->integer('medical_room')->default(0);
            $table->integer('meeting_room')->default(0);
            $table->integer('library_room')->default(0);
            $table->integer('laboratory_quantity')->default(0);
            $table->decimal('laboratory_area', 8, 2)->default(0);
            
            // Công trình vệ sinh - Giáo viên
            $table->integer('teacher_toilet_quantity')->default(0);
            $table->decimal('teacher_toilet_area', 8, 2)->default(0);
            
            // Công trình vệ sinh - Học sinh
            $table->integer('student_toilet_quantity')->default(0);
            $table->decimal('student_toilet_area', 8, 2)->default(0);
            
            // Sân chơi
            $table->integer('playground_quantity')->default(0);
            $table->decimal('playground_area', 8, 2)->default(0);
            
            // Bãi tập
            $table->integer('practice_field_quantity')->default(0);
            $table->decimal('practice_field_area', 8, 2)->default(0);
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('ward_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
            
            // Indexes
            $table->index(['academic_year', 'ward_id']);
            $table->index(['academic_year', 'school_id']);
            $table->index('school_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elementary_infrastructure_reports');
    }
};
