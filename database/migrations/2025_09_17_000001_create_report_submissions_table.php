<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_group_id');
            $table->unsignedBigInteger('report_detail_id');
            $table->unsignedBigInteger('submitted_by_user_id');
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('ward_id')->nullable();
            $table->unsignedBigInteger('province_id')->nullable();
            $table->string('academic_year')->nullable();

            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            // current stage in workflow: school -> ward -> province
            $table->enum('stage', ['school', 'ward', 'province'])->default('school');
            // status per stage
            $table->enum('status', ['draft', 'submitted', 'under_review', 'returned', 'approved', 'rejected'])
                  ->default('submitted');
            $table->text('note')->nullable();

            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('forwarded_at')->nullable();

            $table->timestamps();

            $table->foreign('report_group_id')->references('id')->on('report_groups')->cascadeOnDelete();
            $table->foreign('report_detail_id')->references('id')->on('report_details')->cascadeOnDelete();
            $table->foreign('submitted_by_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('school_id')->references('id')->on('schools')->nullOnDelete();
            $table->foreign('ward_id')->references('id')->on('geo_units')->nullOnDelete();
            $table->foreign('province_id')->references('id')->on('geo_units')->nullOnDelete();

            $table->index(['stage', 'status']);
            $table->index(['school_id', 'ward_id', 'province_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_submissions');
    }
};


