<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly payroll. A RUN is one branch × one pay period (label carries the
 * Ethiopian-calendar month, dates stay Gregorian per platform convention).
 * Lifecycle: draft → approved → paid; items are recomputed freely while draft
 * and frozen after approval. Tax follows App\Support\EthiopianIncomeTax
 * (Proclamation 1395/2025) + private-sector pension (7% / 11%).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name'); // "Meskerem 2018 E.C."
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft'); // draft|approved|paid
            $table->text('notes')->nullable();

            // Cached totals over items — kept current on every recompute.
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('pension_employee_total', 14, 2)->default(0);
            $table->decimal('pension_employer_total', 14, 2)->default(0);
            $table->decimal('deduction_total', 14, 2)->default(0);
            $table->decimal('net_total', 14, 2)->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index payroll_runs_branch_id_period_start_period_end_unique'
            .' on payroll_runs (branch_id, period_start, period_end) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
