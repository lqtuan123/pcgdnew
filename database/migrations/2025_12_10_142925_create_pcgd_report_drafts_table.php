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
        if (Schema::hasTable('pcgd_report_drafts')) {
            return;
        }

        Schema::create('pcgd_report_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('report_type', ['bao_cao_pcgd', 'bien_ban_pcgd_xmc']);
            $table->json('data')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'report_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pcgd_report_drafts');
    }
};
