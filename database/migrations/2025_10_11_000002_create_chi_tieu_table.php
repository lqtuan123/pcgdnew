<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ChiTieu;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chi_tieu', function (Blueprint $table) {
            $table->id();
            $table->string('ma_chi_tieu')->unique()->comment('Mã chỉ tiêu (ty_le_tre_6_tuoi_vao_lop_1,...)');
            $table->string('ten_chi_tieu')->comment('Tên hiển thị của chỉ tiêu');
            $table->text('mo_ta')->nullable()->comment('Mô tả chi tiết');
            $table->boolean('is_active')->default(1)->comment('Trạng thái hoạt động');
            $table->timestamps();
        });

        // Khởi tạo dữ liệu từ Model
        ChiTieu::initDefaultData();
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tieu');
    }
};

