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
        Schema::create('report_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_detail_id')->constrained('report_details')->onDelete('cascade');
            $table->enum('status', ['required', 'optional', 'disabled'])->default('optional')->comment('Trạng thái: bắt buộc, không bắt buộc, không sử dụng');
            $table->timestamps();
            
            $table->unique('report_detail_id'); // Mỗi báo cáo chỉ có 1 cấu hình
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_configs');
    }
};
