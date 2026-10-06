<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ethnic_minorities', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tên dân tộc
            $table->string('code')->unique(); // Mã dân tộc
            $table->text('description')->nullable(); // Mô tả
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ethnic_minorities');
    }
};
