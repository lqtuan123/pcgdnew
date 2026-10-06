<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('logos', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('logo'); // logo | favicon | email
            $table->string('name')->nullable(); // Tên logo
            $table->text('description')->nullable(); // Mô tả
            $table->string('original_path');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('logos');
    }
};
