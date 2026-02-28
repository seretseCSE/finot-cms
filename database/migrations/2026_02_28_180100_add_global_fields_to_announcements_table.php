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
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('status');
            $table->string('target_audience', 50)->default('all_users')->after('is_global');
            $table->json('broadcast_channels')->nullable()->after('target_audience');
            $table->json('acknowledged_by')->nullable()->after('broadcast_channels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['is_global', 'target_audience', 'broadcast_channels', 'acknowledged_by']);
        });
    }
};
