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
        Schema::create('statistical_formulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_detail_id')->constrained('report_details')->onDelete('cascade');
            $table->string('criteria')->comment('Tiêu chí');
            $table->string('formula')->comment('Công thức');
            $table->text('description')->nullable()->comment('Mô tả');
            $table->boolean('is_active')->default(true)->comment('Có sử dụng hay không');
            $table->integer('sort_order')->default(0)->comment('Thứ tự sắp xếp');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistical_formulas');
    }
};
