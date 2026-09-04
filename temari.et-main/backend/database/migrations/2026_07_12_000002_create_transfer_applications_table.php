<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parent/student-initiated transfer applications (the NEMIS-style order):
 * the family applies ONLINE to the destination school; if the destination
 * accepts (placing the student into a year + grade), the application
 * materializes into a standard student_transfer_requests row and the CURRENT
 * school keeps the final say — its approval is the fee clearance. Until
 * acceptance the destination sees only directory-level facts.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('transfer_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            // Who filed it: a linked guardian's account or the student's own.
            $table->foreignId('applicant_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('applicant_parent_id')->nullable()->constrained('parents')->nullOnDelete();

            // Where the student is now (live enrollment at application time).
            $table->foreignId('from_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('from_school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();

            // The destination the family picked (active Temari school/branch).
            $table->foreignId('to_school_id')->constrained('schools')->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();

            $table->string('status')->default('submitted'); // submitted|accepted|declined|withdrawn
            $table->text('reason');
            $table->text('decline_note')->nullable();
            // The standard transfer request minted on acceptance — tracking
            // follows ITS status from that point on.
            $table->foreignId('transfer_request_id')->nullable()->constrained('student_transfer_requests')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['to_branch_id', 'status']);
            $table->index('student_id');
            $table->index('applicant_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_applications');
    }
};
