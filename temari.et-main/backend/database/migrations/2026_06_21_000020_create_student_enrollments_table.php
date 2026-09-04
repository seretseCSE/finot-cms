<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Time-scoped record of where/when a student studies — the ONLY table that ties
 * a student to a school/branch (students themselves are global persons,
 * ADR-011). One row per student per academic year per program, anchoring them
 * to a section (which carries the grade level). `grade_level_id` is denormalized
 * from the section for fast queries and year-end promotions.
 *
 * `school_program_id` enables dual enrollment (regular + evening in the same
 * year). At most one ACTIVE enrollment per (student, year, program) — enforced
 * by a partial unique index so historical (promoted/withdrawn) rows never block
 * re-enrollment.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_program_id')->constrained()->restrictOnDelete();
            // Nullable: at registration many schools know the grade but assign
            // the section later. grade_level_id is authoritative either way
            // (copied from the section when one is given).
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            // Where the student studied before this enrollment — a row in the
            // platform-wide school directory (Temari and non-Temari schools).
            $table->foreignId('previous_school_id')->nullable()->constrained('school_directory')->nullOnDelete();
            $table->string('status')->default('active');
            $table->date('enrolled_on')->nullable();
            $table->date('exited_on')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'academic_year_id']);
            // School-level rollups (profile stats, list counts) filter by status.
            $table->index(['school_id', 'status']);
            $table->index(['branch_id', 'academic_year_id']);
            $table->index(['section_id', 'academic_year_id']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX student_enrollments_one_active
            ON student_enrollments (student_id, academic_year_id, school_program_id)
            WHERE status IN ('pending', 'active') AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
