<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WHERE a grade book applies — one row per grade group. Each row targets a
 * single grade (null = every grade), optionally narrowed to a set of that
 * grade's sections and/or a set of subjects. A null/empty id-array means "all"
 * on that axis. The candidate set for one branch + term is a handful of plans,
 * so section_ids/subject_ids are matched in the app (never SQL-filtered) — the
 * same id-array pattern the LMS uses for quizzes.question_ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuous_assessment_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('continuous_assessment_id')->constrained()->cascadeOnDelete();
            // Null = all grades (an all-grades row is exclusive — the only row).
            $table->foreignId('grade_level_id')->nullable()->constrained()->cascadeOnDelete();
            // Null/empty = all sections of the grade / all subjects.
            $table->jsonb('section_ids')->nullable();
            $table->jsonb('subject_ids')->nullable();
            $table->timestamps();

            $table->index('continuous_assessment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuous_assessment_targets');
    }
};
