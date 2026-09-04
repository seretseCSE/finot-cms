<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A withdrawal from the tutor wallet. Tutor requests → Temari.et staff
 * approve (wallet debited = funds reserved) → paid via Chapa transfer or
 * recorded manually. Account details are SNAPSHOTTED at request time —
 * later profile edits never redirect an in-flight payout.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutor_payouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20)->default('chapa'); // chapa | manual
            $table->string('bank_code', 20)->nullable();
            $table->string('bank_name', 80)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->string('account_name', 120)->nullable();
            $table->string('status', 12)->default('pending'); // PayoutStatus
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('gateway_ref')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['tutor_profile_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_payouts');
    }
};
