<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One week of an annual lesson plan (weeks run Monday-anchored). Carries the
 * approval workflow the user-facing rule hangs on: a teacher may not SUBMIT
 * week N+1 while week N still has uncovered lessons — unless they attach a
 * lag justification, which the reviewer sees front-and-center and may decline.
 * Approved weeks are what families see through /me.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('weekly_lesson_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('annual_lesson_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('week_starts_on');
            $table->string('status', 12)->default('draft');
            // The teacher's explanation when submitting BEHIND schedule —
            // required by the pacing gate, judged by the reviewer.
            $table->text('lag_justification')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decline_reason')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'week_starts_on']);
            $table->index(['term_id', 'week_starts_on']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX weekly_lesson_plans_identity ON weekly_lesson_plans '
            .'(annual_lesson_plan_id, week_starts_on) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_lesson_plans');
    }
};
