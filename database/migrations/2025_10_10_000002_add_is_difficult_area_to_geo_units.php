<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_units', function (Blueprint $table) {
            $table->boolean('is_difficult_area')->default(0)->after('code')->comment('Vùng khó khăn/đặc biệt khó khăn');
        });
    }

    public function down(): void
    {
        Schema::table('geo_units', function (Blueprint $table) {
            $table->dropColumn('is_difficult_area');
        });
    }
};

