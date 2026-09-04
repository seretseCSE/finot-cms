<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The immutable raw scan log — every card tap a device reports, kept even
 * when the card is unknown (diagnostics) or the term is closed (audit).
 * Ingest is idempotent per (device_id, event_uid): offline terminals re-send
 * whole batches after reconnecting and duplicates must be swallowed silently.
 * Attendance is NEVER derived in the ingest request — a queued job resolves
 * cards → holders → attendance rows and stamps the outcome back here.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('device_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('card_uid', 32);
            // Device-side id for idempotency; synthesized from uid+time when
            // the terminal doesn't number its events.
            $table->string('event_uid', 64);
            $table->timestamp('scanned_at');
            $table->timestamp('received_at');
            $table->foreignId('id_card_id')->nullable()->constrained('id_cards')->nullOnDelete();
            $table->nullableMorphs('holder');
            // pending|processed|unknown_card|inactive_card|no_enrollment|closed_term
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->unique(['device_id', 'event_uid']);
            $table->index(['branch_id', 'scanned_at']);
            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_events');
    }
};
