<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teaching CAPABILITY, not assignment: which subject × grade level a teacher
 * can teach (declared on the staff form). Semester assignment generation reads
 * this to pre-fill teachers — the actual teaching row stays subject_assignments.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('teacher_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'subject_id', 'grade_level_id']);
            $table->index(['subject_id', 'grade_level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subjects');
    }
};
