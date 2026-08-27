<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The classes a school course is offered to (ADR-016) — the same audience
 * model as course_material_targets/quiz_targets: one row per targeted
 * subject_assignment. `courses.subject_assignment_id` stays the ANCHOR
 * (ownership) and is always among the targets. Platform and grade-window
 * courses have no rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_assignment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_id', 'subject_assignment_id']);
            $table->index('subject_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_targets');
    }
};
