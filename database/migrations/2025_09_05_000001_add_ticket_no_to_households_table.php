<?php
// database/migrations/2025_09_05_000001_add_ticket_no_to_households_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            // Số phiếu (mã hộ trong phiếu điều tra), cho phép null để tương thích dữ liệu cũ
            $table->string('ticket_no', 100)->nullable()->after('phone');

            // Index phục vụ tra cứu theo “số phiếu + địa bàn”
            $table->index(['ticket_no'], 'hh_ticket_no_idx');
            $table->index(['ticket_no', 'new_ward_id'], 'hh_ticket_new_idx');              // scheme=new
            $table->index(['ticket_no', 'old_district_id', 'old_ward_id'], 'hh_ticket_old_idx'); // scheme=old
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex('hh_ticket_no_idx');
            $table->dropIndex('hh_ticket_new_idx');
            $table->dropIndex('hh_ticket_old_idx');
            $table->dropColumn('ticket_no');
        });
    }
};
