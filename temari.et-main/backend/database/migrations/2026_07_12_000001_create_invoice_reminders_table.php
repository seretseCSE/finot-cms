<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every automated fee reminder sent for an invoice — the dedupe ledger AND
 * the SMS meter (mirrors attendance_notification_logs). `stage` walks the
 * ladder: upcoming (D days before due) → due (on the due date) → overdue_1..N
 * (every M days past due). The unique key guarantees one message per
 * (invoice, recipient, stage, channel) no matter how often the scheduler
 * re-runs. Rows are never deleted: per-school counts feed billing/metering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('audience', 10);  // parent|student
            $table->string('stage', 20);     // upcoming|due|overdue_1..overdue_N
            $table->string('channel', 10);   // sms|email
            $table->string('recipient');     // phone/email snapshot at send time
            $table->string('result', 10)->default('sent'); // sent|failed
            $table->timestamps();

            $table->unique(
                ['invoice_id', 'user_id', 'stage', 'channel'],
                'invoice_reminders_dedupe',
            );
            $table->index(['school_id', 'created_at']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminders');
    }
};
