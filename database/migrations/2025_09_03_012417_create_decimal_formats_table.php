<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('decimal_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Tên cấu hình');
            $table->string('decimal_separator', 1)->default(',')->comment('Dấu tách thập phân');
            $table->string('thousands_separator', 1)->default('.')->comment('Dấu tách hàng nghìn');
            $table->boolean('is_active')->default(false)->comment('Cấu hình đang được sử dụng');
            $table->text('description')->nullable()->comment('Mô tả');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decimal_formats');
    }
};
