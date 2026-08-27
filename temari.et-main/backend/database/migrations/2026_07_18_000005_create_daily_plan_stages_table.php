<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stage table of the MoE daily lesson plan — one row per teaching stage
 * (introduction & motivation / main activities / concluding activities),
 * never a JSON blob: a school may skip a stage and the PDF renders rows in
 * a fixed order regardless of entry order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_plan_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_lesson_plan_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 12); // intro|main|conclusion
            $table->text('learning_contents')->nullable();
            $table->string('page', 30)->nullable();
            $table->text('teacher_activity')->nullable();
            $table->text('student_activity')->nullable();
            $table->text('assessment_techniques')->nullable();
            $table->text('teaching_aids')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->unique(['daily_lesson_plan_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_stages');
    }
};
