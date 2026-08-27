<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online quizzes, exams and mocks — ONE table, `kind` disambiguates
 * (ADR-016; never separate quiz/exam tables). Two anchors, mutually
 * exclusive: `subject_assignment_id` (class quiz/exam, school lane) or
 * `is_platform` (national exam prep, open to any registered user, targeted
 * by subject + grade window). `settings` carries timing/shuffle/navigation/
 * result-reveal policy; `draw` holds random-draw rules (null = fixed list in
 * quiz_questions). `assessment_id` is the gradebook link: graded class
 * quizzes feed assessment_results, never a parallel marks store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_assignment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_platform')->default(false);
            $table->string('kind', 10)->default('quiz');
            $table->string('title');
            $table->text('instructions')->nullable();
            // Platform-lane targeting for the exam-prep browser.
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            // Exam-prep identity (platform lane): what kind of prep paper this
            // is (national_past | mock | practice), the Ethiopian exam year it
            // came from, and the grade 11–12 stream it targets.
            $table->string('exam_kind', 15)->nullable();
            $table->unsignedSmallInteger('exam_year_ec')->nullable();
            $table->string('stream', 10)->nullable();
            $table->string('language', 2)->default('en');
            // Snapshot of the summed question points, frozen at publish.
            $table->decimal('total_points', 8, 2)->default(0);
            $table->jsonb('settings');
            $table->jsonb('draw')->nullable();
            // Paper parts ("Part I — Multiple Choice…"): ordered [{title,
            // instructions}] the fixed picks reference by index. Null = flat.
            $table->jsonb('parts')->nullable();
            // Supervised-exam room code (hashed) — presence is the identity check.
            $table->string('access_code_hash')->nullable();
            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['subject_assignment_id', 'status']);
            $table->index(['is_platform', 'status', 'grade_level_id']);
            $table->index(['is_platform', 'exam_kind', 'exam_year_ec']);
            $table->index(['school_id', 'branch_id']);
        });

        // Global search: staff find LMS content by title from the palette.
        DB::statement('CREATE INDEX quizzes_title_trgm ON quizzes USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
