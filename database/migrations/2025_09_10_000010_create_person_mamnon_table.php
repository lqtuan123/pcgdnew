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
        Schema::create('person_mamnon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->onDelete('cascade');
            
            // Thông tin học tập mầm non
            $table->boolean('hoc_2_buoi')->default(false);
            $table->boolean('hoc_ban_tru')->default(false);
            $table->boolean('hoc_du_ngay_theo_quy_dinh')->default(false);
            $table->boolean('chuyen_can')->default(false);
            
            // Thông tin sức khỏe/dinh dưỡng
            $table->boolean('theo_doi_bd_can_nang')->default(false);
            $table->boolean('sdd_nhe_can')->default(false);
            $table->boolean('theo_doi_bd_chieu_cao')->default(false);
            $table->boolean('sdd_thap_coi')->default(false);
            
            // Thông tin giáo dục mầm non
            $table->boolean('dan_toc_chuan_bi_tv')->default(false);
            $table->boolean('hoan_thanh_chuong_trinh_gdmn')->default(false);
            
            // Thông tin trái tuyến
            $table->boolean('trai_tuyen')->default(false)->comment('Học trái tuyến (ngoài khu vực khảo sát)');
            
            $table->timestamps();
            
            // Index để tối ưu truy vấn
            $table->index('person_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_mamnon');
    }
};