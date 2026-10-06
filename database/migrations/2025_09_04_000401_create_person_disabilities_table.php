<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('person_disabilities', function (Blueprint $table) {
            $table->id();

            // Mỗi người 1 bản ghi
            $table->foreignId('person_id')
                  ->constrained('people')
                  ->cascadeOnDelete()
                  ->unique();

            // Mảng id loại khuyết tật (disability_categories.id), ví dụ: [1,3,5]
            $table->json('disability_ids')
                  ->default(DB::raw('(JSON_ARRAY())'));

            // Cờ tổng quát
            $table->boolean('has_certificate')->default(false)->index(); // có chứng nhận
            $table->boolean('can_learn')->nullable()->index();           // còn khả năng học tập (true/false/null)

            // Ghi chú & trạng thái dùng chung
            $table->text('note')->nullable();
            $table->boolean('is_deleted')->default(false)->index();

            $table->timestamps();

            $table->index(['person_id','is_deleted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_disabilities');
    }
};
