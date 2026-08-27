<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-fee money IN, branch-scoped: hall rental, uniform/book sales, canteen
 * commissions, donations… — everything that is not a student-fee payment
 * (those live in `payments`, always invoice-anchored). No approval
 * lifecycle: recording money received is the cashier's fact, the cashbook
 * and statement show it immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_incomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('finance_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('received_on');
            $table->string('method'); // cash|bank_transfer|wallet|other
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->nullable(); // who paid
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'received_on']);
            $table->index('finance_category_id');
            $table->index('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_incomes');
    }
};
