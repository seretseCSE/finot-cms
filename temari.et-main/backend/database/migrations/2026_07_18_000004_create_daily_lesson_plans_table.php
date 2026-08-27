<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One DAILY lesson plan (the MoE daily format) inside a weekly container:
 * which chapter (FK to the annual unit — alignment is a join, never trusted
 * text), the topic/subtopic, rationale, prerequisite knowledge, objectives
 * and the slow/medium/fast learner supports. The three teaching stages live
 * in daily_plan_stages; the actual classroom sittings (section × date ×
 * period, each with its own coverage) live in daily_plan_deliveries — one
 * plan teaches all of a teacher's sections of the grade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_lesson_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('weekly_lesson_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annual_plan_unit_id')->nullable()->constrained()->nullOnDelete();
            // The anchor day (first delivery); deliveries may repeat the
            // lesson on other days for other sections.
            $table->date('teaches_on');
            $table->string('topic');
            $table->string('subtopic')->nullable();
            $table->text('rationale')->nullable();
            $table->text('prerequisite_knowledge')->nullable();
            $table->text('objectives')->nullable();
            // Support for learners of special need — the differentiation rows.
            $table->text('support_slow')->nullable();
            $table->text('support_medium')->nullable();
            $table->text('support_fast')->nullable();
            $table->text('homework')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);
            $table->timestamps();

            $table->index(['weekly_lesson_plan_id', 'teaches_on', 'sequence']);
            $table->index('annual_plan_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_lesson_plans');
    }
};
