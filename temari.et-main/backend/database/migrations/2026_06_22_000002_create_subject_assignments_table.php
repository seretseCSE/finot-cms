<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The atomic unit of teaching: ONE teacher × ONE subject × ONE section × ONE
 * term (KB §7). Continuous assessments, timetable slots and (later) materials hang off this
 * row, and OWNERSHIP is enforced from it: a teacher with `grades.manage_own`
 * may only mutate assessments of their own assignments.
 *
 * school/branch/academic_year are denormalized from the section/term so scoped
 * authorization never needs a join. Team teaching = one row per teacher for the
 * same (section, subject, term); at most one UNASSIGNED placeholder row per
 * (section, subject, term).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            // null = not yet assigned to a teacher
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('periods_per_week')->default(0);
            // Lesson block length: 1 = single periods, 2/3 = double/triple
            // periods the timetable must place consecutively (labs, practicals).
            $table->unsignedTinyInteger('block_size')->default(1);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'term_id']);
            // School-level rollups (subjects taught) scan by school.
            $table->index('school_id');
            $table->index(['employee_id', 'term_id']);
            $table->index(['section_id', 'term_id']);
        });

        // One row per teacher per (section, subject, term)...
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX subject_assignments_unique_teacher
            ON subject_assignments (section_id, subject_id, term_id, employee_id)
            WHERE employee_id IS NOT NULL AND deleted_at IS NULL
        SQL);

        // ...and at most one unassigned placeholder.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX subject_assignments_unique_unassigned
            ON subject_assignments (section_id, subject_id, term_id)
            WHERE employee_id IS NULL AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_assignments');
    }
};
