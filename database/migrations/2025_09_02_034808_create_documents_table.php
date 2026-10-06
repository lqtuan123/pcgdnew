<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Số hoặc ký hiệu văn bản');
            $table->string('title')->comment('Tên văn bản');
            $table->date('issued_date')->nullable()->comment('Ngày ban hành');
            $table->foreignId('issuer_id')->nullable()->constrained('issuers')->onDelete('set null');
            $table->string('file_path')->nullable()->comment('Đường dẫn file văn bản');
            $table->enum('status', ['mandatory', 'optional', 'inactive'])->default('optional')
                  ->comment('Trạng thái: bắt buộc, không bắt buộc, không sử dụng');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('documents');
    }
};
