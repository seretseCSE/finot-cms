<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid directory placement (the Upwork "boosted" model): a tutor buys a
 * week/month of top-ranked, badged placement through the gateway. Payment
 * activates the boost and extends tutor_profiles.boosted_until; ranking
 * reads that denormalized timestamp, never this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_boosts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 12); // weekly | monthly
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending_payment'); // pending_payment | active | canceled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['tutor_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_boosts');
    }
};
