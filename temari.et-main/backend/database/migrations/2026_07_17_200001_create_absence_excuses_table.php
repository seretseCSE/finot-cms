<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parent-filed absence excuses (relationship lane): a guardian explains a
 * child's absence (sickness, family event) with an optional proof document;
 * branch staff approve or reject. Approval retro-marks the range's absent
 * attendance records as excused — the excuse itself is the audit trail.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('absence_excuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            // The guardian USER who filed it (a parent profile always has one).
            $table->foreignId('requested_by')->constrained('users');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason');
            // Optional proof (medical note…) on R2, served via signed URLs only.
            $table->string('attachment_path', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['student_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_excuses');
    }
};
