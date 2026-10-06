<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('person_contacts', function (Blueprint $table) {
            $table->id();

            // Người "sở hữu" liên hệ (FK đã tự có index)
            $table->foreignId('person_id')
                  ->constrained('people')
                  ->cascadeOnDelete();

            // Họ tên của liên hệ
            $table->string('hoten', 191)->nullable();

            // Quan hệ với hộ (FK đã tự có index)
            $table->foreignId('house_relationship_id')
                  ->nullable()
                  ->constrained('house_relationships')
                  ->nullOnDelete();

            // Điện thoại & ghi chú
            $table->string('dienthoai', 20)->nullable()->index();
            $table->text('note')->nullable();

            $table->timestamps();

            // Nếu muốn: tránh trùng số ĐT trong cùng chủ sở hữu
            // $table->unique(['person_id','dienthoai']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('person_contacts');
    }
};
