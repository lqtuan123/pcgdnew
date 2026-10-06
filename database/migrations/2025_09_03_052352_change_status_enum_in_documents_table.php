<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Cập nhật giá trị cũ sang giá trị mới nếu cần
        DB::table('documents')
            ->where('status', 'mandatory')
            ->update(['status' => 'draft']);
        DB::table('documents')
            ->where('status', 'optional')
            ->update(['status' => 'draft']); // hoặc 'published' tuỳ ý
        DB::table('documents')
            ->where('status', 'inactive')
            ->update(['status' => 'archived']);

        // Sửa ENUM
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', ['draft','published','archived'])
                  ->default('draft')
                  ->comment('Trạng thái: nháp, công bố, lưu trữ')
                  ->change();
        });
    }

    public function down(): void {
        // Quay về ENUM cũ
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', ['mandatory','optional','inactive'])
                  ->default('optional')
                  ->comment('Trạng thái: bắt buộc, không bắt buộc, không sử dụng')
                  ->change();
        });

        // Nếu muốn, có thể đảo ngược dữ liệu cũ
        DB::table('documents')
            ->where('status', 'draft')
            ->update(['status' => 'mandatory']);
        DB::table('documents')
            ->where('status', 'published')
            ->update(['status' => 'optional']);
        DB::table('documents')
            ->where('status', 'archived')
            ->update(['status' => 'inactive']);
    }
};
