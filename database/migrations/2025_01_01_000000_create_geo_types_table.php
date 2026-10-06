<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('geo_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level');                // 1=province, 2=district, 3=commune, 4=village
            $table->string('key', 32);                           // 'province' | 'district' | 'commune' | 'village'
            $table->string('variant', 32);                       // 'tinh','tptw','quan','huyen','xa','phuong','thon','ap',...
            $table->string('title_vi');                          // nhãn đầy đủ: "Thành phố trực thuộc TW"
            $table->string('short_vi')->nullable();              // viết tắt: "TP."
            $table->boolean('use_prefix')->default(true);        // true: "TP. Hà Nội"; false: "Hà Nội (TP.)"
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['level','key','variant']);
            $table->index(['level','key']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('geo_types');
    }
};
