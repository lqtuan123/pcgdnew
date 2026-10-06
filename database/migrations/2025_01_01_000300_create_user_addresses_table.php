<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('geo_unit_id')->constrained('geo_units')->cascadeOnDelete();
            $table->enum('scheme', ['old','new']); // scheme của geo_unit_id tại thời điểm lưu

            // chi tiết địa chỉ tự do
            $table->string('line1')->nullable();     // số nhà/đường
            $table->string('line2')->nullable();     // khu/ấp/...
            $table->string('postal_code')->nullable();
            $table->date('effective_at')->nullable();

            // snapshot để in chứng từ (tuỳ chọn)
            $table->string('snapshot_old')->nullable();
            $table->string('snapshot_new')->nullable();

            $table->timestamps();

            $table->index(['scheme','geo_unit_id']);
            $table->index('user_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('user_addresses');
    }
};
