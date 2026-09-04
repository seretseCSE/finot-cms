<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audience RULES for channel conversations — the same targeting philosophy as
 * quiz_targets / course_targets. One row per (scope × audience): membership is
 * resolved at read time against live enrollments / memberships / positions,
 * so channel access follows transfers and position changes automatically and
 * a school-wide channel never materializes 10k participant rows.
 *
 *  - audience 'staff'    → active memberships in the scope (job_title narrows
 *                          to one position, e.g. the Teachers department group)
 *  - audience 'parents'  → active guardians of active enrollments in scope
 *  - audience 'students' → student portal users of active enrollments in
 *                          scope (only when the branch enables student chat)
 *
 * Scope = the conversation's school, narrowed by branch_id → grade_level_id →
 * section_id when set (most specific wins; NULLs mean "whole school").
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('conversation_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 16); // staff | parents | students
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('job_title', 40)->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index(['branch_id', 'audience']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_targets');
    }
};
