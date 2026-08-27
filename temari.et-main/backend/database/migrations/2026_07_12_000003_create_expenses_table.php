<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money OUT, branch-scoped: one row per expense with its category, payment
 * channel (and account snapshot for bank/wallet), payee and evidence
 * reference. Lifecycle pending → approved/rejected (`finance.books.approve`
 * countersigns; the recorder never approves their own row). Only approved
 * rows count in the cashbook and the income–expense statement. Payroll is
 * NOT recorded here — approved payroll runs flow into the books read-time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('finance_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('method'); // cash|bank_transfer|wallet|other
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payee')->nullable();
            $table->string('reference')->nullable(); // cheque / transaction no.
            $table->string('note')->nullable();

            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('review_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'expense_date']);
            $table->index(['branch_id', 'status']);
            $table->index('finance_category_id');
            $table->index('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
