<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planned spend per (branch, academic year, expense category) — the "budget
 * vs actual" line the statement report compares approved expenses against.
 * One row per cell; setting a cell again upserts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('finance_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index budgets_branch_id_academic_year_id_finance_category_id_unique'
            .' on budgets (branch_id, academic_year_id, finance_category_id) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
