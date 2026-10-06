<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('survey_form_details', function (Blueprint $table) {
            $table->id();

            // Thuộc về 1 phiếu điều tra
            $table->foreignId('survey_form_id')
                  ->constrained('survey_forms')
                  ->cascadeOnDelete(); // KHÔNG cần ->index()

            // Người được điều tra
            $table->foreignId('person_id')
                  ->constrained('people')
                  ->restrictOnDelete(); // KHÔNG cần ->index()

            // Thông tin cư trú
            $table->foreignId('residence_id')
                  ->nullable()
                  ->constrained('residences')
                  ->nullOnDelete(); // KHÔNG cần ->index()

            // Thông tin học tập
            $table->foreignId('person_education_id')
                  ->nullable()
                  ->constrained('person_educations')
                  ->nullOnDelete(); // KHÔNG cần ->index()

            // Xóa mù chữ
            $table->foreignId('literacy_id')
                  ->nullable()
                  ->constrained('person_literacy')
                  ->nullOnDelete(); // KHÔNG cần ->index()

            // Khuyết tật (PersonDisability: JSON disability_ids + has_certificate + can_learn)
            $table->foreignId('person_disability_id')
                  ->nullable()
                  ->constrained('person_disabilities')
                  ->nullOnDelete(); // KHÔNG cần ->index()

            // Hoàn cảnh đặc biệt
            $table->foreignId('person_special_circumstance_id')
                  ->nullable()
                  ->constrained('person_special_circumstances')
                  ->nullOnDelete(); // KHÔNG cần ->index()

            // Thông tin liên lạc
            $table->foreignId('person_contact_id')
                  ->nullable()
                  ->constrained('person_contacts')
                  ->nullOnDelete(); // KHÔNG cần ->index()

            $table->text('note')->nullable();
            $table->string('academic_year', 50)->index();
            $table->boolean('is_deleted')->default(false)->index();

            $table->timestamps();

            // Mỗi người chỉ xuất hiện 1 lần trong 1 phiếu
           $table->unique(
                  ['survey_form_id','person_id','academic_year'],
                  'sfd_form_person_year_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_form_details');
    }
};
