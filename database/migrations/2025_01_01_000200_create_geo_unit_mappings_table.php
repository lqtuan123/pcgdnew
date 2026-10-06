<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('geo_unit_mappings', function (Blueprint $table) {
            $table->id();

            // Cặp ánh xạ: từ bản ghi "cũ" -> bản ghi "mới"
            $table->foreignId('old_unit_id')->constrained('geo_units')->cascadeOnDelete();
            $table->foreignId('new_unit_id')->constrained('geo_units')->cascadeOnDelete();

            $table->enum('relation', ['unchanged','renamed','merged','split','moved','other'])
                  ->default('unchanged');

            $table->decimal('weight', 6, 3)->nullable(); // 0..1 cho trường hợp gộp/tách
            $table->string('note', 255)->nullable();
            $table->date('effective_date')->nullable();

            $table->timestamps();

            $table->unique(['old_unit_id','new_unit_id']);
            $table->index('relation');
        });
    }

    public function down(): void {
        Schema::dropIfExists('geo_unit_mappings');
    }
};
