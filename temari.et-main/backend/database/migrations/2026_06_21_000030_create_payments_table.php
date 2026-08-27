<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A payment recorded against an invoice. `method` is an Ethiopian channel.
 * Temari takes NO cut of school fee payments (revenue is subscriptions —
 * parent-paid platform/AI plans and the optional School Plan — never a
 * percentage of the school's money).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method');
            // Snapshot of the collection account at payment time (from the
            // fee structure unless overridden) — historical record, never
            // retro-updated when the fee is re-pointed.
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            // Official receipt: RCT-{branch}-{seq} from the branch's counter,
            // plus an unguessable token backing the receipt's public QR
            // verification page (same pattern as transfer/withdrawal letters).
            $table->string('receipt_number', 40)->unique();
            $table->string('receipt_token', 64)->unique();
            $table->date('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'paid_at']);
            $table->index('invoice_id');
            // Per-student payment history (/me fees, student profile).
            $table->index('student_id');
            // Per-account collection stats (Postgres does not index FKs itself).
            $table->index('bank_account_id');
        });

        // Global search: finance pastes a bank transaction reference or the
        // printed receipt number ("RCT-12-000123") into ⌘K and lands on the
        // payment's invoice — contains-match, index-backed.
        DB::statement('CREATE INDEX payments_reference_trgm ON payments USING gin (reference gin_trgm_ops)');
        DB::statement('CREATE INDEX payments_receipt_number_trgm ON payments USING gin (receipt_number gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
