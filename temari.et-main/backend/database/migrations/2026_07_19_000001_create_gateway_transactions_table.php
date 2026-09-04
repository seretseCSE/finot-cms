<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every online collection through a payment gateway (Chapa / Telebirr /
 * CBE Birr), for any purpose (tutoring cycles, AI subscription, boosts,
 * School Plan). One audit trail for all of Temari.et's own money — school
 * fees never appear here. Rows are NEVER deleted (financial record); the
 * payable is polymorphic and fulfils itself exactly once on `paid`.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('gateway_transactions', function (Blueprint $table): void {
            $table->id();
            // Our reference, sent to the gateway and printed on receipts.
            $table->string('tx_ref', 40)->unique();
            $table->string('gateway', 20); // chapa | telebirr | cbebirr | fake
            $table->string('purpose', 30); // GatewayPurpose
            $table->morphs('payable');
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // the payer
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('ETB');
            $table->string('status', 20)->default('initiated'); // GatewayTransactionStatus
            $table->text('checkout_url')->nullable();
            // The gateway's own id for this charge (Chapa ref_id, Telebirr
            // prepay/order id…) — what support quotes when calling them.
            $table->string('gateway_ref')->nullable();
            $table->jsonb('raw')->nullable(); // last verify/webhook payload
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['gateway', 'status']);
            $table->index(['purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_transactions');
    }
};
