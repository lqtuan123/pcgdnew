<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            // Thêm cột và FK (nullable, on delete set null)
            $table->foreignId('priority_category_id')
                ->nullable()
                // ->after('...') // nếu muốn đặt vị trí cột thì mở dòng này và điền tên cột phía trước
                ->constrained('priority_categories')
                ->nullOnDelete(); // ON DELETE SET NULL
        });
    }

    public function down(): void
    {
        // Xoá FK và cột nếu tồn tại
        if (Schema::hasColumn('people', 'priority_category_id')) {
            Schema::table('people', function (Blueprint $table) {
                // Tên ràng buộc mặc định: people_priority_category_id_foreign
                $table->dropForeign(['priority_category_id']);
                $table->dropColumn('priority_category_id');
            });
        }
    }
};
