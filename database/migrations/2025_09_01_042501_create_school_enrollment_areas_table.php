<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('school_enrollment_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->cascadeOnDelete();
            $table->foreignId('geo_id')->constrained('geo_units')->cascadeOnDelete();
            $table->year('year'); // Năm tuyển sinh
            $table->timestamps();

            $table->unique(['school_id', 'geo_id', 'year']); // tránh trùng
        });
    }

    public function down(): void {
        Schema::dropIfExists('school_enrollment_areas');
    }
};
