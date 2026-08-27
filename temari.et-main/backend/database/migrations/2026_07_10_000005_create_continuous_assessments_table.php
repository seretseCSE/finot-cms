<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The grade book TEMPLATE (assessment plan): a principal/director defines,
 * per branch + term, the assessment slots that apply (e.g. Quiz 10 /
 * Assignment 15 / Mid 25 / Final 50). WHERE it applies is expressed by the
 * plan's targeting rows (continuous_assessment_targets) — any mix of grade →
 * sections → subjects. Teachers never define structure where a grade book
 * applies — their marklists materialise assessments from
 * continuous_assessment_items, keeping the whole branch consistent and weights
 * summing to 100.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuous_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Applicability lives in continuous_assessment_targets (grade →
            // sections → subjects rows) — not a single window on the plan.
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['branch_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuous_assessments');
    }
};
