<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The branch's grade × program offering matrix: which grades each of the
 * branch's education programs is offered in (e.g. Grade 1 → Regular + Night).
 * Branch identity lives on school_programs.branch_id — no redundant FK here.
 * A newly provisioned program starts with every grade attached (Regular × all
 * grades on branch creation); the branch editor narrows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_level_school_program', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_program_id')->constrained()->cascadeOnDelete();

            $table->unique(['grade_level_id', 'school_program_id']);
            $table->index('school_program_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_level_school_program');
    }
};
