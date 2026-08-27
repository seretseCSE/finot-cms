<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_assignment_id')->constrained()->cascadeOnDelete();
            // The grade-book template slot this row materialises, when the
            // branch runs principal-defined grade books. Null = teacher ad-hoc
            // assessment (only allowed where no grade book applies). The FK
            // constraint attaches in the continuous_assessment_items migration — that
            // table is created later in the migration order.
            $table->unsignedBigInteger('continuous_assessment_item_id')->nullable();
            // quiz, test, mid_exam, final_exam, assignment, project
            $table->string('type', 30);
            $table->string('name');
            $table->decimal('max_score', 6, 2);
            // percentage weight within the subject for this term
            $table->decimal('weight', 5, 2)->default(0);
            $table->date('conducted_on')->nullable();
            $table->softDeletes();
            $table->timestamps();

            // One materialised row per template slot per assignment.
            $table->unique(['subject_assignment_id', 'continuous_assessment_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
