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
        Schema::table('survey_form_details', function (Blueprint $table) {
            $table->unsignedBigInteger('person_mamnon_id')->nullable()->after('person_education_id');
            $table->foreign('person_mamnon_id')->references('id')->on('person_mamnon')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('survey_form_details', function (Blueprint $table) {
            $table->dropForeign(['person_mamnon_id']);
            $table->dropColumn('person_mamnon_id');
        });
    }
};
