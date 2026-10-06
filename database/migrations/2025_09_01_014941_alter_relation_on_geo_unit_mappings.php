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
        // Sử dụng Schema builder thay vì raw SQL để tương thích với SQLite
        Schema::table('geo_unit_mappings', function (Blueprint $table) {
            $table->string('relation', 32)->nullable()->change();
            $table->date('effective_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('geo_unit_mappings', function (Blueprint $table) {
            //
        });
    }
};
