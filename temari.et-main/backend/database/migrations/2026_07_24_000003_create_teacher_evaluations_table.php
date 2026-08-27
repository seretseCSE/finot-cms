<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One employee's performance appraisal for one term (the Ethiopian
 * per-semester ritual): draft (evaluator scoring) → submitted (shared with
 * the teacher) → acknowledged (teacher signed, optional comment). Overall
 * score is out of 100 (criterion weights × score/max). One official record
 * per employee × term.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('draft'); // draft | submitted | acknowledged
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('improvements')->nullable();
            $table->text('teacher_comment')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'term_id', 'status']);
            $table->index(['employee_id', 'term_id']);
        });

        DB::statement(
            'create unique index teacher_evaluations_employee_term_unique'
            .' on teacher_evaluations (employee_id, term_id) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_evaluations');
    }
};
