<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academic years are branch-scoped (ADR-004): every branch runs its own
 * calendar. `name` is the Ethiopian-calendar label (e.g. "2017 E.C."). Dates are
 * stored as Gregorian; Ethiopian-calendar rendering happens at the app layer.
 * Lifecycle: planned (next year, being set up) → active (the running year —
 * at most one per branch) → completed → archived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('planned');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index academic_years_branch_id_name_unique'
            .' on academic_years (branch_id, name) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
