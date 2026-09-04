<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One student's turn-in for an assignment. The row is updated in place on
 * resubmission (`attempt_count` tracks how many turn-ins, enforced against
 * the assignment's resubmission_policy); `files` is display-only R2 metadata
 * behind signed URLs; `link_url` carries link submissions. `rubric_scores`
 * mirrors the assignment's rubric ({criterion → points}) and sums into
 * `score`. Late is stamped server-side against due_at.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body')->nullable();
            $table->jsonb('files')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(1);
            $table->timestamp('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->string('status', 12)->default('submitted');
            $table->decimal('score', 6, 2)->nullable();
            $table->jsonb('rubric_scores')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
            $table->index(['assignment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
