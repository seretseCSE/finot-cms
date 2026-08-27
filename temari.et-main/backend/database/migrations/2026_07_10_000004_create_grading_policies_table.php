<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which grading scale applies where: per school (branch_id null = the school
 * default) or per branch (override), for a grade-level window in
 * grade_levels.sort_order terms — the same windowing as subjects. `display`
 * decides what report cards show for that window: raw numbers, letters, or
 * both. Resolution (GradingPolicyResolver): branch row → school row →
 * platform percentage scale shown numerically. Windows never overlap within
 * one scope (validated at write time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            // null = school-wide default; non-null = branch override.
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('grading_scale_id')->constrained()->restrictOnDelete();
            // numeric | letter | both
            $table->string('display', 10)->default('numeric');
            // Applicability window (grade_levels.sort_order, 1=KG1 … 16=G12).
            // Null bound = open-ended on that side.
            $table->unsignedSmallInteger('min_grade_sort')->nullable();
            $table->unsignedSmallInteger('max_grade_sort')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_policies');
    }
};
