<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('people', function (Blueprint $table) {
            $table->id();

            // Họ đệm + Tên
            $table->string('ho_dem', 120)->nullable()->index();
            $table->string('ten', 60)->index();

            // Ngày, tháng, năm sinh (lưu rời theo form)
            $table->unsignedTinyInteger('dob_day')->nullable();    // 1..31
            $table->unsignedTinyInteger('dob_month')->nullable();  // 1..12
            $table->unsignedSmallInteger('dob_year')->nullable();  // ví dụ 1900..2100

            // Giới tính: cột "Nữ" trong biểu mẫu -> boolean
            $table->boolean('is_female')->default(false)->index();

            // Dân tộc (chuỗi) — có thể tách ra bảng riêng sau
            $table->foreignId('ethnicity_id')->nullable()->constrained('ethnicities')->nullOnDelete();

            // Tôn giáo — liên kết bảng religions bạn đã có
            $table->foreignId('religion_id')->nullable()->constrained('religions')->nullOnDelete();

            // Diện ưu tiên (mã/nhóm) — tạm thời string, sau có thể tách bảng
            $table->string('identity_number', 20)->nullable()->index();
            // Điện thoại + cờ xóa mềm theo nghiệp vụ
            $table->string('phone', 20)->nullable()->index();
            $table->string('so_phieu', 20)->nullable()->index();
            $table->string('addressline', 191)->nullable();

            // Khóa ngoại hành chính (tùy bạn đã có bảng gì)
            // Nếu bạn có bảng 'wards' và 'districts' thì bật constrained(); nếu chưa, giữ dạng bigInteger + index.
            $table->unsignedBigInteger('old_ward_id')->nullable()->index();
            $table->unsignedBigInteger('old_province_id')->nullable()->index();
            $table->unsignedBigInteger('new_province_id')->nullable()->index();
            $table->unsignedBigInteger('new_ward_id')->nullable()->index();
            $table->unsignedBigInteger('old_district_id')->nullable()->index();
            $table->unsignedBigInteger('village_id')->nullable()->index();
            $table->string('contact_name', 100)->nullable();
            $table->integer('residence_status_id')
                  ->nullable();
            $table->integer('residence_type_id')
                  ->nullable();
             $table->string('note')->nullable();
            $table->boolean('is_deleted')->default(false)->index();

            $table->timestamps();

            // Tối ưu tìm kiếm theo họ tên
            $table->index(['ho_dem','ten']);
            // Tối ưu lọc theo năm/tháng/ngày sinh
            $table->index(['dob_year','dob_month','dob_day']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('people');
    }
};
