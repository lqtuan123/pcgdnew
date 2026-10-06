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
        Schema::create('students_off_track', function (Blueprint $table) {
            $table->id();
            
            // Thông tin cá nhân
            $table->string('ho_dem')->nullable()->comment('Họ đệm');
            $table->string('ten')->nullable()->comment('Tên');
            $table->integer('dob_day')->nullable()->comment('Ngày sinh');
            $table->integer('dob_month')->nullable()->comment('Tháng sinh');
            $table->integer('dob_year')->nullable()->comment('Năm sinh');
            $table->string('identity_number')->nullable()->comment('Số định danh cá nhân');
            $table->boolean('is_female')->default(false)->comment('Nữ');
            
            // Thông tin tham chiếu (ID) - nối sang CSDL hiện có
            $table->unsignedBigInteger('ethnicity_id')->nullable()->comment('Dân tộc');
            $table->unsignedBigInteger('religion_id')->nullable()->comment('Tôn giáo');
            $table->unsignedBigInteger('priority_category_id')->nullable()->comment('Diện ưu tiên');
            $table->unsignedBigInteger('education_block_id')->nullable()->comment('Khối học');
            $table->unsignedBigInteger('school_id')->nullable()->comment('Mã trường');
            $table->unsignedBigInteger('graduation_level_id')->nullable()->comment('Bậc tốt nghiệp');
            
            // Mã địa bàn cũ (3 cấp từ Excel) - nơi ở từ Excel
            $table->string('stay_province_code_old')->nullable()->comment('Mã tỉnh/thành cũ (nơi ở từ Excel)');
            $table->string('stay_district_code_old')->nullable()->comment('Mã quận/huyện cũ (nơi ở từ Excel)');
            $table->string('stay_ward_code_old')->nullable()->comment('Mã phường/xã cũ (nơi ở từ Excel)');
            
            // Mã địa bàn mới (2 cấp, bỏ quận/huyện) - nơi ở từ Excel
            $table->string('stay_province_code_new')->nullable()->comment('Mã tỉnh/thành mới (nơi ở từ Excel)');
            $table->string('stay_ward_code_new')->nullable()->comment('Mã phường/xã mới (nơi ở từ Excel)');
            
            // Mã địa bàn từ select box (khu vực được chọn)
            $table->string('selected_province_code_old')->nullable()->comment('Mã tỉnh/thành cũ (khu vực chọn)');
            $table->string('selected_district_code_old')->nullable()->comment('Mã quận/huyện cũ (khu vực chọn)');
            $table->string('selected_ward_code_old')->nullable()->comment('Mã phường/xã cũ (khu vực chọn)');
            $table->string('selected_village_code_old')->nullable()->comment('Mã thôn/xóm cũ (khu vực chọn)');
            $table->string('selected_province_code_new')->nullable()->comment('Mã tỉnh/thành mới (khu vực chọn)');
            $table->string('selected_ward_code_new')->nullable()->comment('Mã phường/xã mới (khu vực chọn)');
            $table->string('selected_village_code_new')->nullable()->comment('Mã thôn/xóm mới (khu vực chọn)');
            
            // Thông tin học tập - lưu trực tiếp
            $table->string('class_label')->nullable()->comment('Lớp học');
            $table->boolean('hoc_hai_buoi')->default(false)->comment('Học 2 buổi/ngày');
            $table->boolean('hoc_9_10_buoi_tuan')->default(false)->comment('Học đầy đủ theo quy định');
            $table->boolean('is_bo_tuc')->default(false)->comment('Bổ túc');
            $table->string('graduation_year')->nullable()->comment('Năm tốt nghiệp');
            $table->unsignedBigInteger('bo_hoc_lop')->nullable()->comment('Bỏ học lớp (ID khối học)');
            $table->integer('bo_hoc_nam')->nullable()->comment('Bỏ học năm');
            
            // Thông tin khuyết tật - lưu trực tiếp (không nối sang bảng khuyết tật)
            $table->boolean('disability_van_dong')->default(false)->comment('Khuyết tật vận động');
            $table->boolean('disability_nghe_noi')->default(false)->comment('Khuyết tật nghe, nói');
            $table->boolean('disability_nhin')->default(false)->comment('Khuyết tật nhìn');
            $table->boolean('disability_than_kinh')->default(false)->comment('Khuyết tật thần kinh, tâm thần');
            $table->boolean('disability_tri_tue')->default(false)->comment('Khuyết tật trí tuệ');
            $table->boolean('disability_hoc_tap')->default(false)->comment('Khuyết tật học tập');
            $table->boolean('disability_tu_ky')->default(false)->comment('Tự kỷ');
            $table->boolean('disability_khac')->default(false)->comment('Khuyết tật khác');
            $table->boolean('disability_has_certificate')->default(false)->comment('Có chứng nhận khuyết tật');
            $table->string('disability_can_learn')->nullable()->comment('Khả năng học tập');
            
            // Hoàn cảnh đặc biệt - lưu trực tiếp (không nối sang bảng hoàn cảnh)
            $table->string('special_circumstance_name')->nullable()->comment('Hoàn cảnh đặc biệt');
            $table->text('special_circumstance_detail')->nullable()->comment('Chi tiết hoàn cảnh đặc biệt');
            
            // Thông tin import
            $table->string('academic_year')->nullable()->comment('Năm học');
            $table->string('import_batch')->nullable()->comment('Lô import');
            
            // Thông tin quản lý
            $table->boolean('is_deleted')->default(false)->comment('Trạng thái xóa');
            $table->unsignedBigInteger('editor_id')->nullable()->comment('ID người thực hiện');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['academic_year', 'import_batch']);
            $table->index(['ethnicity_id']);
            $table->index(['religion_id']);
            $table->index(['priority_category_id']);
            $table->index(['education_block_id']);
            $table->index(['school_id']);
            $table->index(['graduation_level_id']);
            $table->index(['stay_province_code_old']);
            $table->index(['stay_district_code_old']);
            $table->index(['stay_ward_code_old']);
            $table->index(['stay_province_code_new']);
            $table->index(['stay_ward_code_new']);
            $table->index(['selected_province_code_old']);
            $table->index(['selected_district_code_old']);
            $table->index(['selected_ward_code_old']);
            $table->index(['selected_village_code_old']);
            $table->index(['selected_province_code_new']);
            $table->index(['selected_ward_code_new']);
            $table->index(['selected_village_code_new']);
            $table->index(['is_deleted']);
            $table->index(['editor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students_off_track');
    }
};
