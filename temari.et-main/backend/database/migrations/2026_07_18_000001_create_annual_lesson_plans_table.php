<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The teacher's yearly roadmap for ONE subject × grade — a teacher covering
 * four Grade 9 Math sections writes a single annual plan; weekly plans hang
 * off it. Goes through the same submit → approve/decline ritual as marklists
 * (director OR principal decides, each independently) and becomes the pacing
 * baseline everything else is measured against: its units carry the chapter
 * timeline, weekly plans must tally with them.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('annual_lesson_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            // Overall goals + designated teaching methods (sanitized rich text).
            $table->text('goals')->nullable();
            $table->text('methods')->nullable();
            // The MoE annual-plan header: teaching load declared up front —
            // the unit grid's allotted periods are checked against the total.
            $table->unsignedTinyInteger('periods_per_week')->nullable();
            $table->unsignedSmallInteger('total_periods')->nullable();
            $table->string('status', 12)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decline_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'academic_year_id', 'status']);
            $table->index(['employee_id', 'academic_year_id']);
        });

        // One live plan per teacher × subject × grade × year (soft-deleted
        // rows never block a fresh start).
        DB::statement(
            'CREATE UNIQUE INDEX annual_lesson_plans_identity ON annual_lesson_plans '
            .'(academic_year_id, branch_id, subject_id, grade_level_id, employee_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_lesson_plans');
    }
};
