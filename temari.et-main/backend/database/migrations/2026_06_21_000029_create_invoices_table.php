<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A bill issued to a student for an academic year/term. `amount_paid` is kept in
 * sync as payments land; `status` is derived (unpaid/partial/paid/scholarship/void).
 * `fee_structure_id` is nullable so ad-hoc invoices are possible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fee_structure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);

            // Scholarship/discount applied to THIS invoice (never a boolean on
            // the student): none | percentage | fixed | full_scholarship. The payable
            // net is derived (Invoice::netAmount()); `amount` stays the gross so
            // reporting and history remain exact.
            $table->string('discount_type', 20)->default('none');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->string('scholarship_reason')->nullable();
            // Provenance when the discount came from a standing concession
            // (resolved at generation time) rather than a manual grant.
            $table->foreignId('fee_concession_id')->nullable()->constrained()->restrictOnDelete();

            // Recurring billing: the ETHIOPIAN month this invoice bills
            // (e.g. 2018/1 = Meskerem 2018; quarterly stamps the period's
            // FIRST month). Null on non-period invoices. The recurring
            // engine's idempotency lives on the partial unique index below —
            // a student is billed a given period exactly once.
            $table->unsignedSmallInteger('billing_year')->nullable();
            $table->unsignedSmallInteger('billing_month')->nullable();

            // Late penalty accrued by fees:apply-penalties from the fee's
            // penalty config. Lives OUTSIDE amount/discount — the payable
            // total is net(amount, discount) + penalty. Waiving zeroes it
            // and blocks further accrual.
            $table->decimal('penalty_amount', 12, 2)->default(0);
            $table->boolean('penalty_waived')->default(false);

            $table->string('status')->default('unpaid');
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            // School-wide (All branches) finance lists filter by school alone.
            $table->index(['school_id', 'status']);
            $table->index('student_id');
            $table->index(['fee_structure_id', 'term_id']);
            $table->index(['branch_id', 'due_date']);
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index invoices_billing_period_unique'
            .' on invoices (fee_structure_id, student_id, billing_year, billing_month) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
