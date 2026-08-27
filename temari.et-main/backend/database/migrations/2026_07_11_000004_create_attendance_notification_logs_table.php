<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every attendance alert sent to a guardian — the dedupe ledger AND the SMS
 * meter. The unique key guarantees a guardian is texted at most once per
 * (student, day, status, channel) no matter how many times a register is
 * re-saved or a device syncs late. Rows are never deleted: per-school counts
 * feed billing/metering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_user_id')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->string('status', 10);  // absent|late — the mark notified
            $table->string('channel', 10); // sms|email|inapp
            $table->string('recipient');   // phone/email snapshot at send time
            $table->string('result', 10)->default('sent'); // sent|failed
            $table->timestamps();

            $table->unique(
                ['student_id', 'date', 'status', 'guardian_user_id', 'channel'],
                'attendance_notification_dedupe'
            );
            $table->index(['school_id', 'created_at']);
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_notification_logs');
    }
};
