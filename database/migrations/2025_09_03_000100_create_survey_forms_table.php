<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('survey_forms', function (Blueprint $table) {
            $table->id();

            $table->string('so_phieu', 50)->unique();
            $table->string('academic_year', 50);
            // Chủ hộ (Person)
            $table->integer('head_person_id')
                ->nullable(); // KHÔNG cần ->index()

            // MẢNG người điều tra: ["Nguyễn A", "Trần B", ...]
            $table->json('investigators')->default(DB::raw('(JSON_ARRAY())'));

            // Cờ xóa chung hệ thống
            $table->boolean('is_deleted')->default(false)->index();

            $table->timestamps();

            $table->index(['head_person_id']);
            $table->unsignedBigInteger('old_ward_id')->nullable()->index();
            $table->unsignedBigInteger('new_ward_id')->nullable()->index();
            $table->unsignedBigInteger('old_district_id')->nullable()->index();
            $table->unsignedBigInteger('village_id')->nullable()->index();
            $table->unsignedBigInteger('old_province_id')->nullable()->index();
            $table->unsignedBigInteger('new_province_id')->nullable()->index();
            $table->integer('editor_id')->nullable();
            $table->timestamp('surveyed_at')->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('survey_forms');
    }
};
