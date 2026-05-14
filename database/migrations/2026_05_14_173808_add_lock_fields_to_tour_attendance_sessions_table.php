<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_attendance_sessions', function (Blueprint $table) {
            $table->dateTime('locked_at')->nullable()->after('status');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete()->after('locked_at');
            $table->text('locked_reason')->nullable()->after('locked_by');
        });

        DB::statement("ALTER TABLE tour_attendance_sessions MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Open'");
    }

    public function down(): void
    {
        Schema::table('tour_attendance_sessions', function (Blueprint $table) {
            $table->dropColumn(['locked_at', 'locked_by', 'locked_reason']);
        });
    }
};
