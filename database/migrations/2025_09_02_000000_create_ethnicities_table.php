<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void
    {
        Schema::create('ethnicities', function (Blueprint $table) {
        $table->id();
        $table->string('code')->nullable()->unique();
        $table->string('name')->unique();
        $table->boolean('is_minority')->default(true)->index();
        $table->string('slug')->unique();
        $table->text('note')->nullable();
        $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('ethnicities');
    }
};