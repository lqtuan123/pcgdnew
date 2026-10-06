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
        Schema::create('secondary_management_report', function (Blueprint $table) {
            $table->id();
            $table->string('academic_year'); // Năm học
            $table->unsignedBigInteger('ward_id'); // ID phường/xã
            $table->unsignedBigInteger('school_id'); // ID trường học
            $table->string('school_name'); // Tên trường học
            
            // Tổng số và phân loại
            $table->integer('total'); // Tổng số
            $table->integer('female'); // Nữ
            $table->integer('party_member'); // Đảng viên
            
            // Trình độ chuyên môn
            $table->integer('above_university'); // Trình độ chuyên môn trên đại học
            $table->integer('university'); // Trình độ chuyên môn Đại học
            $table->integer('college'); // Trình độ chuyên môn Cao đẳng
            $table->integer('secondary'); // Trình độ chuyên môn Trung học
            
            // Môn học
            $table->integer('mathematics'); // Toán
            $table->integer('physics'); // Lý
            $table->integer('chemistry'); // Hóa
            $table->integer('biology'); // Sinh
            $table->integer('literature'); // Văn
            $table->integer('history'); // Sử
            $table->integer('geography'); // Địa
            $table->integer('english'); // NN Anh
            $table->integer('french'); // NN Pháp
            $table->integer('russian'); // NN Nga
            $table->integer('other_language'); // NN khác
            $table->integer('civic_education'); // GDCD
            $table->integer('technology'); // Công nghệ
            $table->integer('physical_education'); // Thể dục
            $table->integer('music'); // Âm nhạc
            $table->integer('art'); // Mỹ thuật
            $table->integer('informatics'); // Tin học
            
            // Cán bộ quản lý
            $table->integer('principal'); // Hiệu trưởng
            $table->integer('vice_principal'); // Phó hiệu trưởng
            
            $table->timestamps();
            
            // Indexes
            $table->index(['academic_year', 'ward_id']);
            $table->index(['academic_year', 'school_id']);
            $table->foreign('ward_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secondary_management_report');
    }
};
