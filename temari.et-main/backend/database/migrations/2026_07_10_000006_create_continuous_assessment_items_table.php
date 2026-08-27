<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One assessment slot of a grade book template (type, name, weight within the
 * term's 100, max raw score, optional due date). Materialised into concrete
 * `assessments` rows per subject assignment when a teacher opens their
 * marklist. Also wires the assessments → continuous_assessment_items FK here (the
 * assessments table is created earlier in the migration order, so the
 * constraint can only attach once this table exists).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuous_assessment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('continuous_assessment_id')->constrained()->cascadeOnDelete();
            // quiz, test, assignment, project, mid_exam, final_exam
            $table->string('type', 30);
            $table->string('name');
            $table->decimal('weight', 5, 2);
            $table->decimal('max_score', 6, 2);
            $table->date('due_on')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreign('continuous_assessment_item_id')
                ->references('id')->on('continuous_assessment_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['continuous_assessment_item_id']);
        });

        Schema::dropIfExists('continuous_assessment_items');
    }
};
