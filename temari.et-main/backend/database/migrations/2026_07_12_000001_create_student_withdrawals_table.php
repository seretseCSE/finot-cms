<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A mid-year withdrawal: the student leaves the school entirely or moves to a
 * school OUTSIDE Temari (in-platform moves go through student_transfer_requests
 * instead). One row per withdrawn enrollment — it freezes the reason, the
 * destination the family named and the outstanding balance at the moment of
 * withdrawal, and backs the printable QR-verified clearance letter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->unique()->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();

            $table->text('reason');
            // Free-text destination ("moving to Bahir Dar", a non-Temari school
            // name…) — printed on the clearance letter when given.
            $table->string('destination')->nullable();
            $table->date('withdrawn_on');
            // Outstanding balance snapshot at withdrawal time (open invoices'
            // net minus paid). The letter notes it; it is never a blocker —
            // the RTE-style rule: issue the letter, record the debt.
            $table->decimal('outstanding_amount', 12, 2)->default(0);

            // Unguessable token behind the PUBLIC letter URL (QR verification)
            // — minted the first time the letter is opened.
            $table->string('public_token', 64)->nullable()->unique();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'withdrawn_on']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_withdrawals');
    }
};
