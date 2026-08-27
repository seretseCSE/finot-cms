<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-platform student transfers (OpenEMIS-style): the RECEIVING branch
 * requests, the SENDING branch decides — its approval is also the fee-
 * clearance gate. Until approval the receiving school sees only directory-
 * level facts about the student; approval closes the old enrollment, opens
 * the new one and writes the student_promotions audit row in one transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('from_school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            // Where the student lands on approval: a year at the receiving
            // branch + the grade the receiving registrar places them into.
            $table->foreignId('to_academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('to_grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('to_enrollment_id')->nullable()->constrained('student_enrollments')->restrictOnDelete();

            $table->string('status')->default('requested'); // requested|approved|rejected|cancelled
            // The student's file AS THEY LEFT, frozen at approval (profile/
            // address, health, guardians, document ids). The sending school's
            // read-only archive view is served from THIS snapshot — never from
            // the live record, which belongs to the receiving school after the
            // handover (ADR-017).
            $table->jsonb('handover_snapshot')->nullable();
            // Unguessable token behind the PUBLIC letter URL (QR verification)
            // — minted the first time the approved letter is opened.
            $table->string('public_token', 64)->nullable()->unique();
            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['from_branch_id', 'status']);
            $table->index(['to_branch_id', 'status']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_transfer_requests');
    }
};
