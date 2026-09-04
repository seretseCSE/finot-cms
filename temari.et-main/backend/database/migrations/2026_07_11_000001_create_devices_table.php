<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RFID attendance terminals (13.56MHz MIFARE). Branch-scoped — a branch may
 * run several (gates, staff room); one device can serve students, employees
 * or both (`audience`). Devices authenticate on their own machine lane with a
 * per-device bearer token (hashed here, revealed once at creation/rotation) —
 * never via user memberships. Offline terminals queue scans and flush them to
 * POST /device/events when data returns.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('location')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('audience', 10); // students|employees|both
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'branch_id']);
        });

        // attendance_records.device_id is declared in its own (earlier) create
        // migration; the constraint can only attach once devices exists.
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropForeign(['device_id']);
        });

        Schema::dropIfExists('devices');
    }
};
