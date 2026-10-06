<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();

            // Thông tin cơ bản
            $table->string('full_name');
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('birth_date')->nullable();
            
            // Thông tin trường/mầm non
            $table->unsignedBigInteger('school_id'); // liên kết với bảng schools
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('cascade');

            // Hợp đồng và loại hợp đồng
            $table->unsignedBigInteger('contract_type_id')->nullable(); // liên kết với bảng contract_types
            $table->foreign('contract_type_id')->references('id')->on('contract_types')->onDelete('set null');
            $table->boolean('receiving_benefits')->default(false); // Được hưởng chế độ, chính sách

            // Thông tin cá nhân
            $table->string('identity_number')->nullable(); // CMND/CCCD
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();

            // Dân tộc
            $table->unsignedBigInteger('ethnic_minority_id')->nullable(); // liên kết với bảng ethnic_minorities
            $table->foreign('ethnic_minority_id')->references('id')->on('ethnic_minorities')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('teachers');
    }
};
