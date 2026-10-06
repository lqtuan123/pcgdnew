<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();       // Mã trường
            $table->string('name');                 // Tên trường
            $table->foreignId('school_type_id')    // Loại hình trường
                  ->constrained('school_types');
            $table->foreignId('educational_level_id') // Bậc học
                  ->constrained('educational_levels');
            $table->string('ward_code', 64)->nullable();  // Mã phường/xã
            $table->string('district_code', 64)->nullable(); // Mã quận/huyện
            $table->string('province_code', 64)->nullable();  // Mã phường/xã
            $table->string('province_code_new', 64)->nullable(); // Mã quận/huyện
            $table->unsignedBigInteger('old_ward_id')->nullable()->index();
            $table->unsignedBigInteger('new_ward_id')->nullable()->index();
            $table->unsignedBigInteger('old_province_id')->nullable()->index();
            $table->unsignedBigInteger('new_province_id')->nullable()->index();
            $table->unsignedBigInteger('old_district_id')->nullable()->index();
            $table->unsignedBigInteger('village_id')->nullable()->index();
            $table->integer('is_conglap')->default(1) ;
            $table->integer('is_datchuan')->default(0) ;
            $table->timestamps();

            $table->index(['ward_code', 'district_code']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('schools');
    }
};
