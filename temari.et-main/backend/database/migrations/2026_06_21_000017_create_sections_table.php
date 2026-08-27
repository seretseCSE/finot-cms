<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A section is a stable class division within a branch + grade level (e.g.
 * "Grade 1 — A"). It is NOT year-scoped; student enrollments tie a section to a
 * specific academic year/term. The homeroom teacher IS year-scoped, so it
 * lives in `section_homerooms`, never on the section row. `capacity` caps how
 * many students may be enrolled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->string('name'); // "A", "B", "Blue", ...
            $table->string('room_number', 30)->nullable();
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'grade_level_id']);
            // School-level rollups (grade span, section counts) scan by school.
            $table->index(['school_id', 'grade_level_id']);
        });

        // Partial unique: trashed rows must not block recreating the same
        // name (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index sections_branch_id_grade_level_id_name_unique'
            .' on sections (branch_id, grade_level_id, name) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
