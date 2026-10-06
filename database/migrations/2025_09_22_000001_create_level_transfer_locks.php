<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('level_transfer_locks', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type'); // province|district|ward|school
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('academic_year');
            $table->unsignedBigInteger('locked_by');
            $table->timestamp('locked_at');
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'academic_year'], 'lvl_transfer_lock_unique');
            $table->index(['academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_transfer_locks');
    }
};


