<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('school_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Loại hình trường
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('school_types');
    }
};
