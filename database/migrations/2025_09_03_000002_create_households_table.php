<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('households', function (Blueprint $table) {
            $table->id();

            // Tên hộ gia đình (mặc định sẽ “copy” theo tên chủ hộ ở tầng Model)
            $table->string('name', 191)->nullable()->index();

            // Địa chỉ số nhà - tên đường
            $table->string('addressline', 191)->nullable();

            // Khóa ngoại hành chính (tùy bạn đã có bảng gì)
            // Nếu bạn có bảng 'wards' và 'districts' thì bật constrained(); nếu chưa, giữ dạng bigInteger + index.
            $table->unsignedBigInteger('old_ward_id')->nullable()->index();
            $table->unsignedBigInteger('new_ward_id')->nullable()->index();
            $table->unsignedBigInteger('old_province_id')->nullable()->index();
            $table->unsignedBigInteger('new_province_id')->nullable()->index();
            $table->unsignedBigInteger('old_district_id')->nullable()->index();
            $table->unsignedBigInteger('village_id')->nullable()->index();
          
            $table->string('phone', 20)->nullable();
            $table->boolean('is_deleted')->default(false)->index();
            $table->integer('residence_status_id')
                  ->nullable();
            $table->integer('residence_type_id')
                  ->nullable();   
            // Chủ hộ
            $table->foreignId('head_person_id')
                  ->nullable()
                  ->constrained('people')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('households');
    }
};
