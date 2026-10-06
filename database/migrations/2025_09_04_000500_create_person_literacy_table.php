<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('person_literacy', function (Blueprint $table) {
            $table->id();

            $table->foreignId('person_id')
                  ->constrained('people')
                  ->cascadeOnDelete()
                  ->unique(); // mỗi người 1 bản ghi

            // 3 trường theo mẫu
            $table->unsignedTinyInteger('dang_hoc_lop')->nullable()
                  ->comment('Số lớp đang học (nếu có)');
            $table->unsignedTinyInteger('hoan_thanh_lop')->nullable()
                  ->comment('Đã hoàn thành đến lớp mấy');
            $table->unsignedTinyInteger('tai_mu_chu_muc')->nullable()
                  ->comment('Mức tái mù chữ: 0=không, 1/2/3...');

            $table->timestamps();

            // tìm nhanh theo mức/lớp
            $table->index(['dang_hoc_lop']);
            $table->index(['hoan_thanh_lop']);
            $table->index(['tai_mu_chu_muc']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('person_literacy');
    }
};
