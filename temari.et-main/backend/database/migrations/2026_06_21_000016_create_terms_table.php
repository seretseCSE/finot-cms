<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Terms (semesters/quarters) belong to an academic year — Ethiopian schools
 * typically run two semesters, but a year may hold 1–5 (quarters, summer…).
 * `term_id` is the universal time anchor for all academic records, so it
 * carries denormalized school_id/branch_id for fast scoped queries.
 * Exactly one term per branch may be `is_current`. Each term runs under one
 * education program (regular / night / distance …) of its branch.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('school_program_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('sequence'); // 1–5
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // Daily class window + how long one period runs (minutes).
            $table->time('class_starts_at')->nullable();
            $table->time('class_ends_at')->nullable();
            $table->unsignedSmallInteger('period_minutes')->default(45);
            $table->boolean('is_quarter')->default(false);
            // Which semester a QUARTER belongs to (1|2) — groups quarter terms
            // for semester sub-averages on the yearly roster; null for
            // semester-sized terms (they ARE the semester).
            $table->unsignedTinyInteger('semester')->nullable();
            $table->boolean('is_current')->default(false);
            // Lifecycle: planned -> active -> closed. A CLOSED term is read-only
            // for every academic record anchored to it (enforced centrally by
            // App\Support\TermGate) - the KB access rule made structural.
            $table->string('status')->default('planned');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_current']);
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index terms_academic_year_id_sequence_unique'
            .' on terms (academic_year_id, sequence) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
