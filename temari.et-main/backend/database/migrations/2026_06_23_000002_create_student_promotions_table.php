<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Year-end decisions (promoted / repeated / transferred / graduated /
 * withdrawn) — the audit trail AND the promotion-board worksheet. A row is
 * DECIDED when saved from the board (decided_by/decided_at set, to_enrollment
 * empty) and EXECUTED once the year rollover creates the next enrollment
 * (executed_at + to_* filled). Enrollment `status` says what a row IS; this
 * table says WHO decided WHAT and WHEN, and links the from/to enrollments so
 * a student's academic history is walkable across years and schools.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_promotions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            // Denormalized from the from-enrollment so the board and rollover
            // never join to filter by year.
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_enrollment_id')->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('to_enrollment_id')->nullable()->constrained('student_enrollments')->restrictOnDelete();
            $table->foreignId('from_grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('to_grade_level_id')->nullable()->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->string('decision'); // promoted|repeated|transferred|graduated|withdrawn
            // Annual average snapshot at decision time (evidence the decision
            // was made on — results may be recomputed later).
            $table->decimal('average', 5, 2)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('student_id');
            // One decision per source enrollment — re-deciding updates in place.
            $table->unique('from_enrollment_id');
            $table->index(['academic_year_id', 'from_branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
