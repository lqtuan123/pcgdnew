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
        Schema::create('gdmn_finance', function (Blueprint $table) {
            $table->id();
            $table->year('year')->comment('Năm ngân sách');
            
            // Liên kết với đơn vị địa lý (tỉnh và xã)
            $table->unsignedBigInteger('province_id')->nullable()->comment('ID tỉnh/thành phố');
            $table->unsignedBigInteger('ward_id')->comment('ID xã/phường');
            $table->foreign('province_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('ward_id')->references('id')->on('geo_units')->onDelete('cascade');

            // Mã mục và tiểu mục (đặt cố định trong code)
            $table->unsignedTinyInteger('main_item')->comment('Mã mục chi chính');
            $table->unsignedTinyInteger('sub_item')->nullable()->comment('Mã tiểu mục chi');

            // Số tiền chi - dùng string để lưu đúng format người dùng nhập (4, 4.1, 4,1)
            $table->string('amount', 50)->default('0')->comment('Số tiền chi');
            $table->string('unit', 50)->default('Tr.đồng')->comment('Đơn vị tính');
            $table->text('note')->nullable()->comment('Ghi chú');
            
            $table->timestamps();

            $table->unique(['year', 'ward_id', 'main_item', 'sub_item'], 'uniq_year_ward_item');
            $table->index(['year', 'ward_id']);
            $table->index(['year', 'province_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gdmn_finance');
    }
};
