<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('education_grades', function (Blueprint $table) {
            $table->id();

            $table->foreignId('education_level_id')
                  ->nullable()                       // bắt buộc nếu dùng SET NULL
                  ->constrained('graduation_levels')  // refs graduation_levels(id)
                  ->nullOnDelete()                   // ON DELETE SET NULL
                  ->cascadeOnUpdate();               // ON UPDATE CASCADE

            $table->string('code', 20);
            $table->string('name', 100);
            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::table('education_grades', function (Blueprint $table) {
            $table->dropForeign(['education_level_id']);
        });
        Schema::dropIfExists('education_grades');
    }
};
