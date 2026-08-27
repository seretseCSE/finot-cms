<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One chapter/unit of an annual lesson plan — row per unit, never a JSON
 * blob: the planned date window and period count are the pacing baseline the
 * director's dashboard queries against, and weekly plan lessons FK here so
 * "which chapter is this week teaching" is a join, not a text match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_plan_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('annual_lesson_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // The term the unit is taught in — semester grouping for the grid.
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('title');
            $table->text('objectives')->nullable();
            $table->text('methods')->nullable();
            // The MoE annual-grid columns: why the unit matters, what it
            // builds on, how it is taught/assessed, and the textbook window.
            $table->text('rationale')->nullable();
            $table->text('prerequisite_knowledge')->nullable();
            $table->text('teaching_aids')->nullable();
            $table->text('assessment_techniques')->nullable();
            $table->unsignedSmallInteger('page_from')->nullable();
            $table->unsignedSmallInteger('page_to')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('planned_periods')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['annual_lesson_plan_id', 'sequence']);
            $table->index(['branch_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annual_plan_units');
    }
};
