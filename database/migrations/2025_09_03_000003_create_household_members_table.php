<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('household_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('household_id')
                  ->constrained('households')
                  ->cascadeOnDelete();

            $table->foreignId('person_id')
                  ->constrained('people')
                  ->cascadeOnDelete();

            // Vai trò trong hộ: head/spouse/child/other...
            $table->string('role', 20)->default('other')->index();

            $table->timestamps();

            // Mỗi người chỉ thuộc 1 hộ (nếu đúng nghiệp vụ của bạn), bật unique:
            $table->unique('person_id');

            // (Không thể enforce "chỉ một head trong mỗi hộ" ở DB dễ dàng;
            // sẽ handle ở tầng ứng dụng.)
        });
    }

    public function down(): void {
        Schema::dropIfExists('household_members');
    }
};
