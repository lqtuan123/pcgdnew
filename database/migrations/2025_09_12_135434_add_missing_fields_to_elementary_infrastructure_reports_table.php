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
        Schema::table('elementary_infrastructure_reports', function (Blueprint $table) {
            // Thêm các trường mới theo cấu trúc ảnh
            $table->string('school_branch')->nullable()->after('school_name'); // Điểm trường
            $table->integer('total_classes')->default(0)->after('school_branch'); // Tổng số lớp
            $table->integer('combined_classes')->default(0)->after('total_classes'); // Lớp ghép
            $table->integer('rented_classrooms')->default(0)->after('temporary_classrooms'); // Thuê/mượn
            $table->integer('youth_union_room')->default(0)->after('medical_room'); // TT HĐ Đội
            
            // Phòng họp (SL + DT)
            $table->integer('meeting_room_quantity')->default(0)->after('youth_union_room');
            $table->decimal('meeting_room_area', 8, 2)->default(0)->after('meeting_room_quantity');
            
            // Thư viện (SL + DT) - thay thế library_room
            $table->integer('library_room_quantity')->default(0)->after('meeting_room_area');
            $table->decimal('library_room_area', 8, 2)->default(0)->after('library_room_quantity');
            
            // Thiết bị (SL + DT)
            $table->integer('equipment_room_quantity')->default(0)->after('library_room_area');
            $table->decimal('equipment_room_area', 8, 2)->default(0)->after('equipment_room_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('elementary_infrastructure_reports', function (Blueprint $table) {
            $table->dropColumn([
                'school_branch',
                'total_classes', 
                'combined_classes',
                'rented_classrooms',
                'youth_union_room',
                'meeting_room_quantity',
                'meeting_room_area',
                'library_room_quantity',
                'library_room_area',
                'equipment_room_quantity',
                'equipment_room_area'
            ]);
        });
    }
};