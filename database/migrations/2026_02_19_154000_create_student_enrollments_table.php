<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            $table->date('enrolled_date');
            $table->date('completion_date')->nullable();

            $table->enum('status', ['Enrolled', 'Withdrawn', 'Completed', 'Promoted'])->default('Enrolled');
            $table->enum('withdrawal_reason', ['Moved Away', 'Transferred', 'Graduated', 'Other'])->nullable();
            $table->text('withdrawal_notes')->nullable();

            $table->foreignId('enrolled_by')->constrained('users');
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['member_id', 'class_id', 'academic_year_id'], 'se_member_class_year_unique');
            $table->index(['member_id', 'academic_year_id', 'status']);
            $table->index(['class_id', 'academic_year_id', 'status']);
        });

        // Enforce only one Enrolled enrollment per member per academic year.
        // DB-dependent; application validation also enforces this.
        try {
            DB::statement("CREATE UNIQUE INDEX se_one_enrolled_per_year ON student_enrollments (member_id, academic_year_id, (CASE WHEN status = 'Enrolled' THEN 1 ELSE NULL END))");
        } catch (\Throwable $e) {
            // Ignore if DB doesn't support functional indexes.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP INDEX se_one_enrolled_per_year ON student_enrollments');
        } catch (\Throwable $e) {
        }

        Schema::dropIfExists('student_enrollments');
    }
};
