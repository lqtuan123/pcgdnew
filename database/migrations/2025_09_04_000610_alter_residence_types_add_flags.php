<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('residence_types', function (Blueprint $table) {
            if (!Schema::hasColumn('residence_types','code')) {
                $table->string('code', 10)->nullable()->unique()->after('id'); // KT1..KT4
            }
            if (!Schema::hasColumn('residence_types','is_phai_pho_cap')) {
                $table->boolean('is_phai_pho_cap')->default(false)->after('name')->index();
            }
        });

        // Backfill gợi ý theo tên hiện có
        // Phải phổ cập: KT1 (Thường trú), KT3 (Tạm trú)
        // Không phải phổ cập: KT2, KT4 (tạm trú ngắn hạn/ lưu trú)
        DB::table('residence_types')
            ->whereIn('name', ['Thường trú','KT1'])
            ->update(['code' => 'KT1', 'is_phai_pho_cap' => true]);

        DB::table('residence_types')
            ->whereIn('name', ['Tạm trú','KT3'])
            ->update(['code' => 'KT3', 'is_phai_pho_cap' => true]);

        DB::table('residence_types')
            ->whereIn('name', ['KT2','Tạm trú ngắn hạn'])
            ->update(['code' => 'KT2', 'is_phai_pho_cap' => false]);

        DB::table('residence_types')
            ->whereIn('name', ['KT4','Lưu trú'])
            ->update(['code' => 'KT4', 'is_phai_pho_cap' => false]);
    }

    public function down(): void {
        Schema::table('residence_types', function (Blueprint $table) {
            if (Schema::hasColumn('residence_types','is_phai_pho_cap')) {
                $table->dropColumn('is_phai_pho_cap');
            }
            if (Schema::hasColumn('residence_types','code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }
        });
    }
};
