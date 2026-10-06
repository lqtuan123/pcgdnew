<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('residences', function (Blueprint $table) {
            $table->id();

            // Người cư trú
            $table->foreignId('person_id')
                  ->constrained('people')
                  ->cascadeOnDelete(); // FK tự tạo index, không cần ->index()

            // Chủ hộ (lưu person_id của chủ hộ)
            $table->foreignId('head_person_id')
                  ->nullable()
                  ->constrained('people')
                  ->nullOnDelete();    // Không cần ->index()

            // Loại cư trú
            
            // Trạng thái cư trú
            $table->integer('residence_status_id')
                  ->nullable();
            $table->integer('residence_type_id')
                  ->nullable();   

            // Quan hệ trong hộ
             $table->integer('house_relationship_id')
                  ->nullable();   

            

            $table->timestamps();

            // Tối ưu truy vấn kết hợp
            $table->index(['person_id','residence_status_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('residences');
    }
};
