<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ChuongTrinh;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chuong_trinh', function (Blueprint $table) {
            $table->id();
            $table->string('ma_chuong_trinh')->unique()->comment('Mã chương trình (pcgd_muc_1, xmc_muc_2, pcgd_thcs)');
            $table->string('ten_chuong_trinh')->comment('Tên đầy đủ chương trình (PCGD Mức 1, XMC Mức 2)');
            $table->text('mo_ta')->nullable()->comment('Mô tả chi tiết');
            $table->boolean('is_active')->default(1)->comment('Trạng thái hoạt động');
            $table->timestamps();
        });

        // Khởi tạo dữ liệu từ Model
        ChuongTrinh::initDefaultData();
    }

    public function down(): void
    {
        Schema::dropIfExists('chuong_trinh');
    }
};
