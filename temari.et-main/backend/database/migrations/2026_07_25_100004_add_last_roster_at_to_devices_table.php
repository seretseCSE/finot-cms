<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Terminals verify cards OFFLINE against a locally cached roster they pull
 * from GET /device/roster (daily). This stamp is the sync telemetry: a device
 * that heartbeats but never pulls its roster is misconfigured, and the panel
 * must be able to say so.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->timestamp('last_roster_at')->nullable()->after('last_event_at');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropColumn('last_roster_at');
        });
    }
};
