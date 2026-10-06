<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // cờ giải thể: mặc định false (chưa giải thể)
            $table->boolean('is_dissolved')
                  ->default(false)
                  ->after('district_code')
                  ->index(); // thường sẽ lọc theo cờ này, nên index luôn
        });
    }

    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            // tên index tự động của Laravel: {table}_{column}_index
            $table->dropIndex('schools_is_dissolved_index');
            $table->dropColumn('is_dissolved');
        });
    }
};
