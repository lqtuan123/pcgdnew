<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('logo_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logo_id')->constrained()->cascadeOnDelete();
            $table->integer('width');
            $table->integer('height');
            $table->string('path');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('logo_sizes');
    }
};
