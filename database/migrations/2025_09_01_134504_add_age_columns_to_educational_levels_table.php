<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('educational_levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('age_min')->nullable()->after('name');
            $table->unsignedTinyInteger('age_max')->nullable()->after('age_min');
        });
    }

    public function down(): void {
        Schema::table('educational_levels', function (Blueprint $table) {
            $table->dropColumn(['age_min', 'age_max']);
        });
    }
};
