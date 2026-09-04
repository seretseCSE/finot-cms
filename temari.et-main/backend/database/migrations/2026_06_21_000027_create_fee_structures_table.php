<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a branch charges for an academic year: a named fee with an ETB amount and
 * a TYPE (registration / one_time / daily / weekly / monthly / quarterly /
 * semester / yearly). Registration fees are the simplest — name, year, amount,
 * grades. Every other type may carry a billing window (starts_on → due_on),
 * parent/student notifications, and a late penalty (fixed, or incremental every
 * N days). Applicable grades live on `fee_structure_grade_level` — empty pivot
 * = all grades. Money is decimal(12,2) — never varchar (School-X mistake).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('fee_structures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type', 20)->default('one_time');
            $table->decimal('amount', 12, 2);
            // Collection accounts live on `fee_structure_bank_account` (0..n).
            // Payments SNAPSHOT their account, so re-pointing a fee later never
            // rewrites payment history.

            // Billing window (not used by registration fees).
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();

            // Recurring types (monthly/quarterly): the ETHIOPIAN day-of-month
            // (1–30) each period's invoice falls due. Null = app default (10).
            $table->unsignedSmallInteger('billing_day')->nullable();
            // Recurring types: auto-issue each period's invoices on schedule
            // (fees:generate-recurring). Off = finance generates manually.
            $table->boolean('auto_generate')->default(false);

            // Who gets an SMS when the due date approaches/arrives.
            $table->boolean('notify_parents')->default(false);
            $table->boolean('notify_students')->default(false);

            // Late penalty: fixed = one flat amount after due; incremental =
            // +amount every `penalty_increment_days` days past due.
            $table->string('penalty_type', 20)->nullable();
            $table->decimal('penalty_amount', 12, 2)->nullable();
            $table->unsignedSmallInteger('penalty_increment_days')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'academic_year_id']);
            $table->index(['branch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structures');
    }
};
