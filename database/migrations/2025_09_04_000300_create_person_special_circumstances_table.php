<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('person_special_circumstances', function (Blueprint $table) {
            $table->id();

            // Người
            $table->foreignId('person_id')
                  ->constrained('people')
                  ->cascadeOnDelete(); // KHÔNG cần ->index()

            // Loại hoàn cảnh (FK -> special_circumstances)
            $table->foreignId('sc_id')
                  ->constrained('special_circumstances')
                  ->restrictOnDelete(); // KHÔNG cần ->index()

            $table->text('chi_tiet')->nullable();

            $table->timestamps();

            // Ngăn trùng một loại hoàn cảnh cho cùng một người
            $table->unique(['person_id','sc_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('person_special_circumstances');
    }
};
