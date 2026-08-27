<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user, per-day AI consumption. One row per (user, date) — messages and
 * token counts are incremented atomically as responses complete. Quota
 * enforcement (free-tier daily message caps, plan caps) reads today's row;
 * cost telemetry aggregates over it. Dates are Gregorian calendar days in the
 * app timezone — a billing/ops measure, never a user-facing academic date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_ledgers');
    }
};
