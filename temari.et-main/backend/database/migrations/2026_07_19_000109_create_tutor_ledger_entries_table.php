<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tutor wallet, as an APPEND-ONLY ledger: earnings (cycle releases,
 * positive), payouts (negative, reserved at approval), boost fees and
 * operator adjustments. `balance_after` snapshots the running balance —
 * tutor_profiles.wallet_balance mirrors the latest row and both are written
 * ONLY by TutorLedger under a row lock. Never updated, never deleted.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutor_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 20); // earning | payout | payout_reversal | boost_fee | adjustment
            $table->decimal('amount', 12, 2); // signed
            $table->decimal('balance_after', 12, 2);
            $table->nullableMorphs('reference'); // cycle / payout / boost
            $table->string('memo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tutor_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_ledger_entries');
    }
};
