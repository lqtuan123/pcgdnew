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
        Schema::table('report_submissions', function (Blueprint $table) {
            $table->date('deadline')->nullable()->after('academic_year')->comment('Hạn nộp báo cáo');
            $table->text('feedback')->nullable()->after('note')->comment('Phản hồi từ cấp trên');
            $table->timestamp('feedback_at')->nullable()->after('feedback')->comment('Thời gian phản hồi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_submissions', function (Blueprint $table) {
            $table->dropColumn(['deadline', 'feedback', 'feedback_at']);
        });
    }
};
