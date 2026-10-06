<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('issuers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Tên cơ quan ban hành');
            $table->string('code')->nullable()->comment('Mã cơ quan nếu có');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('issuers');
    }
};
