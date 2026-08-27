<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The B2C parent/student AI upgrade (CLAUDE.md §11): a monthly subscription
 * collected through the payment gateway (GatewayPurpose::AiSubscription) —
 * Temari's own money, never school fees. A paid transaction extends
 * `ends_at` by the plan length from max(now, current end) so renewing early
 * never loses days. Entitlement = an active row with ends_at in the future.
 * Financial history — rows are never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 20); // monthly
            $table->decimal('amount', 12, 2);
            $table->string('status', 20); // pending_payment | active | expired
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_subscriptions');
    }
};
