<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('report_submissions')) {
            return;
        }

        Schema::table('report_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('report_submissions', 'province_id')) {
                $table->unsignedBigInteger('province_id')->nullable()->after('ward_id');
                $table->foreign('province_id')->references('id')->on('geo_units')->nullOnDelete();
                $table->index(['province_id']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('report_submissions')) {
            return;
        }

        Schema::table('report_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('report_submissions', 'province_id')) {
                $table->dropForeign(['province_id']);
                $table->dropIndex(['province_id']);
                $table->dropColumn('province_id');
            }
        });
    }
};


