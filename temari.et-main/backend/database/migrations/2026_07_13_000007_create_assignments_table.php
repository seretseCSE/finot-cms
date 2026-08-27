<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Homework/classwork posted by a teacher to one class (ADR-016) — anchored to
 * the subject_assignment like everything a teacher owns. `kind` shapes the
 * work: standard (student turns something in), quiz (a linked quiz IS the
 * work — attempts are the submissions, one source of truth).
 * `submission_types` lists the accepted modes
 * (text/file/photo/audio/link); attachments are display-only metadata
 * for R2 files, never queried. `rubric` is an ordered list of criteria
 * ({criterion, max_points}) whose points sum to max_score when used.
 * `target_student_ids` narrows a post to specific students (null = the whole
 * class). `assessment_id` is the gradebook link: a graded assignment feeds
 * assessment_results, no double entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_assignment_id')->constrained()->cascadeOnDelete();
            // standard | quiz
            $table->string('kind', 12)->default('standard');
            $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->jsonb('submission_types');
            $table->jsonb('attachments')->nullable();
            $table->jsonb('rubric')->nullable();
            $table->jsonb('target_student_ids')->nullable();
            $table->decimal('max_score', 6, 2)->nullable();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('due_at')->nullable();
            // accept | reject; penalty percent docked from late scores when set.
            $table->string('late_policy', 10)->default('accept');
            $table->decimal('late_penalty_percent', 5, 2)->nullable();
            // until_graded | once | never — how often a student may resubmit.
            $table->string('resubmission_policy', 12)->default('until_graded');
            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['subject_assignment_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        // Global search: staff find LMS content by title from the palette.
        DB::statement('CREATE INDEX assignments_title_trgm ON assignments USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
