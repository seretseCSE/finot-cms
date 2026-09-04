<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One employee's payslip inside a run. Every item SNAPSHOTS the employee's
 * positions, allowances and deductions into `breakdown` (JSONB) so later HR
 * edits never rewrite payroll history.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();

            $table->decimal('basic_salary', 12, 2)->default(0);   // sum of active positions
            $table->decimal('allowances_total', 12, 2)->default(0);
            $table->decimal('gross_pay', 12, 2)->default(0);
            $table->decimal('taxable_income', 12, 2)->default(0);
            $table->decimal('income_tax', 12, 2)->default(0);
            $table->decimal('pension_employee', 12, 2)->default(0); // 7%
            $table->decimal('pension_employer', 12, 2)->default(0); // 11%, employer cost — not deducted
            $table->decimal('deductions_total', 12, 2)->default(0); // recurring deduction lines
            $table->decimal('net_pay', 12, 2)->default(0);

            // Snapshot of the lines this item was computed from:
            // { positions: [...], allowances: [...], deductions: [...] }
            $table->jsonb('breakdown');

            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
