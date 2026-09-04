<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The frozen semester report card: one row per enrollment per term holding the
 * computed average, section rank and a JSONB snapshot of the per-subject
 * weighted totals (same freeze pattern as payroll_items.breakdown). Recomputed
 * freely while the term is active; the term-close job writes the final state.
 * Annual averages and promotion suggestions read THESE rows, never the raw
 * assessment_results.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('student_term_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            // Denormalized scope + placement at computation time, so ranked
            // lists and cross-year averages never join.
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();

            $table->decimal('total', 8, 2)->nullable();
            $table->decimal('average', 5, 2)->nullable();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedSmallInteger('rank_of')->nullable();
            $table->unsignedSmallInteger('subject_count')->default(0);
            // Per-subject snapshot: [{subject_id, code, name, total, letter,
            // band_label, is_passing}] — letters resolved through the branch
            // grading policy AT FREEZE TIME and never remapped afterwards.
            $table->jsonb('breakdown')->default('[]');
            // Grading snapshot for the overall average: {scale: {id, code,
            // name}, display, overall: {letter, label, grade_points, is_passing}}.
            $table->jsonb('grading')->nullable();
            // Report-card extras. Conduct (ሥነ ምግባር, A–E by convention) and the
            // homeroom comment are ENTERED by the homeroom teacher and survive
            // recomputes; absence_days is derived from attendance_records.
            $table->string('conduct', 5)->nullable();
            $table->unsignedSmallInteger('absence_days')->nullable();
            $table->string('comment')->nullable();
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['student_enrollment_id', 'term_id']);
            $table->index(['term_id', 'section_id']);
            $table->index(['student_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_term_results');
    }
};
