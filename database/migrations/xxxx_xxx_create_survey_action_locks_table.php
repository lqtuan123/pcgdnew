<?php
// database/migrations/xxxx_xx_xx_create_survey_locks_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('survey_locks', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 32); // SURVEY | STATS_SEND | STATS_INPUT
            $table->enum('geo_level', ['GLOBAL','PROVINCE','WARD','VILLAGE']);
            $table->unsignedBigInteger('geo_id')->nullable(); // null khi GLOBAL
            $table->boolean('is_active')->default(true);
            $table->timestamp('locked_until')->nullable(); // tuỳ chọn
            $table->text('note')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Mỗi scope + địa bàn chỉ có 1 dòng
            $table->unique(['scope','geo_level','geo_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('survey_locks');
    }
};
