<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('geo_units', function (Blueprint $table) {
            $table->id();

            // Loại/biến thể + cấp
            $table->foreignId('geo_type_id')->constrained('geo_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('type_level'); // 1..4 (để lọc nhanh)

            // Cây cha-con
            $table->foreignId('parent_id')->nullable()
                  ->constrained('geo_units')->nullOnDelete();

            // Thông tin
            $table->uuid('uuid')->nullable()->unique();
            $table->string('code', 64)->nullable();              // mã hành chính
            $table->string('name');                              // tên đơn vị
            $table->string('slug')->nullable();

            $table->enum('scheme', ['old','new'])->default('new'); // lược đồ cũ/mới
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['scheme','type_level']);
            $table->index(['parent_id','type_level']);
            $table->unique(['scheme','code']); // code có thể null (OK trong MySQL)
        });
    }

    public function down(): void {
        Schema::dropIfExists('geo_units');
    }
};
