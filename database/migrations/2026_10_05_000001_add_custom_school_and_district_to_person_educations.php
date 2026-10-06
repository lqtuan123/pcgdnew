<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some database backups contain the migration record but not this table.
        // Recreate the original schema so a local restore remains usable.
        if (!Schema::hasTable('person_educations')) {
            Schema::create('person_educations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
                $table->foreignId('education_block_id')->nullable()->constrained('education_blocks')->nullOnDelete();
                $table->foreignId('education_grade_id')->nullable()->constrained('education_grades')->nullOnDelete();
                $table->string('class_label', 50)->nullable()->index();
                $table->boolean('trai_tuyen')->default(false)->index();
                $table->boolean('hoc_hai_buoi')->default(false)->index();
                $table->boolean('hoc_9_10_buoi_tuan')->default(false)->index();
                $table->boolean('is_repeat_grade')->default(false)->index();
                $table->unsignedBigInteger('studying_district_id')->nullable()->index();
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->foreignId('graduation_level_id')->nullable()->constrained('graduation_levels')->nullOnDelete();
                $table->boolean('is_bo_tuc')->default(false)->index();
                $table->string('graduation_year', 9)->nullable();
                $table->foreignId('vocational_level_id')->nullable()->constrained('vocational_levels')->nullOnDelete();
                $table->string('vocational_grad_year', 9)->nullable();
                $table->string('graduation_certificate_no', 100)->nullable();
                $table->unsignedTinyInteger('hoc_xong_lop')->nullable();
                $table->string('hoc_xong_nam', 9)->nullable();
                $table->unsignedTinyInteger('bo_hoc_lop')->nullable();
                $table->string('bo_hoc_nam', 9)->nullable();
                $table->string('academic_year', 9)->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['person_id', 'academic_year']);
            });
        }

        Schema::table('person_educations', function (Blueprint $table) {
            if (!Schema::hasColumn('person_educations', 'studying_district_custom')) {
                $table->string('studying_district_custom')->nullable()->after('studying_district_id');
            }
            if (!Schema::hasColumn('person_educations', 'school_name_custom')) {
                $table->string('school_name_custom')->nullable()->after('school_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('person_educations', function (Blueprint $table) {
            if (Schema::hasColumn('person_educations', 'studying_district_custom')) {
                $table->dropColumn('studying_district_custom');
            }
            if (Schema::hasColumn('person_educations', 'school_name_custom')) {
                $table->dropColumn('school_name_custom');
            }
        });
    }
};
