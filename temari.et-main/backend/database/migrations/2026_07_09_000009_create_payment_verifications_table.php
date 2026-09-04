<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A parent's payment-proof submission for an invoice, checked against bank
 * records via check.et. Every attempt is stored with the provider response
 * snapshot: `verified` auto-records a Payment (payment_id set); `failed` is
 * final (not found / duplicate receipt); `needs_review` parks it for finance
 * staff (amount above balance, receiver account mismatch, provider down).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payment_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();

            // What the parent submitted (one of reference / link / file).
            $table->string('method', 20);
            $table->string('bank_code', 30)->nullable();
            $table->string('transaction_number')->nullable();
            $table->string('receipt_url', 2048)->nullable();
            $table->string('receipt_path', 2048)->nullable();

            $table->string('status', 20);
            $table->string('failure_reason')->nullable();
            $table->jsonb('response')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            // Manual review resolution (needs_review → verified/failed by
            // finance staff): who decided, when, and why.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();

            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index('transaction_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_verifications');
    }
};
