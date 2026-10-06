<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ChuongTrinh;
use App\Models\ChiTieu;
use App\Models\PcgdThcsChiTieu;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chi_tieu_gia_tri', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chuong_trinh_id')->constrained('chuong_trinh')->cascadeOnDelete();
            $table->foreignId('chi_tieu_id')->constrained('chi_tieu')->cascadeOnDelete();
            $table->decimal('khong_kk_dbkk', 5, 2)->nullable()->comment('Chỉ tiêu cho vùng không khó khăn');
            $table->decimal('vung_kk_dbkk', 5, 2)->nullable()->comment('Chỉ tiêu cho vùng khó khăn/đặc biệt khó khăn');
            $table->integer('thu_tu')->default(0)->comment('Thứ tự hiển thị');
            $table->timestamps();

            // Đảm bảo không trùng lặp
            $table->unique(['chuong_trinh_id', 'chi_tieu_id'], 'unique_chuong_trinh_chi_tieu');
        });

        // Migrate dữ liệu từ bảng cũ sang bảng mới cho PCGD THCS
        $pcgdThcs = ChuongTrinh::where('ma_chuong_trinh', 'pcgd_thcs')->first();
        
        if ($pcgdThcs) {
            // Dữ liệu mặc định cho PCGD THCS (từ Model ChiTieu)
            $defaultValues = ChiTieu::GIA_TRI_MAC_DINH_PCGD_THCS;

            $dataMigrated = false;

            // Kiểm tra bảng cũ và migrate dữ liệu nếu có
            if (Schema::hasTable('pcgd_thcs_chi_tieu')) {
                $oldData = PcgdThcsChiTieu::all();
                
                // Chỉ migrate nếu có dữ liệu trong bảng cũ
                if ($oldData->isNotEmpty()) {
                    foreach ($oldData as $index => $old) {
                        $chiTieu = ChiTieu::where('ma_chi_tieu', $old->ma_chi_tieu)->first();
                        
                        if ($chiTieu) {
                            DB::table('chi_tieu_gia_tri')->insert([
                                'chuong_trinh_id' => $pcgdThcs->id,
                                'chi_tieu_id' => $chiTieu->id,
                                'khong_kk_dbkk' => $old->khong_kk_dbkk,
                                'vung_kk_dbkk' => $old->vung_kk_dbkk,
                                'thu_tu' => $index + 1,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $dataMigrated = true;
                        }
                    }
                }
            }

            // Nếu không có dữ liệu từ bảng cũ, dùng giá trị mặc định
            if (!$dataMigrated) {
                $index = 0;
                foreach ($defaultValues as $maChiTieu => $values) {
                    $chiTieu = ChiTieu::where('ma_chi_tieu', $maChiTieu)->first();
                    
                    if ($chiTieu) {
                        DB::table('chi_tieu_gia_tri')->insert([
                            'chuong_trinh_id' => $pcgdThcs->id,
                            'chi_tieu_id' => $chiTieu->id,
                            'khong_kk_dbkk' => $values['khong_kk_dbkk'],
                            'vung_kk_dbkk' => $values['vung_kk_dbkk'],
                            'thu_tu' => ++$index,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tieu_gia_tri');
    }
};

