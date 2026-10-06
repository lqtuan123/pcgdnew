<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_deadlines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_group_id');
            $table->unsignedBigInteger('report_detail_id')->nullable();
            $table->unsignedBigInteger('set_by_user_id'); // User cài đặt deadline
            $table->unsignedBigInteger('target_ward_id')->nullable(); // Ward được cài đặt deadline (nếu Province cài)
            $table->unsignedBigInteger('target_school_id')->nullable(); // School được cài đặt deadline (nếu Ward cài)
            $table->date('deadline');
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('report_group_id')->references('id')->on('report_groups')->onDelete('cascade');
            $table->foreign('report_detail_id')->references('id')->on('report_details')->onDelete('cascade');
            $table->foreign('set_by_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_ward_id')->references('id')->on('geo_units')->onDelete('cascade');
            $table->foreign('target_school_id')->references('id')->on('schools')->onDelete('cascade');
            
            $table->index(['report_group_id', 'target_ward_id', 'target_school_id', 'is_active'], 'rd_deadline_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_deadlines');
    }
};
