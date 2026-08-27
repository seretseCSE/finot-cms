<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user, per-conversation state — read pointer, mute, pin — created LAZILY
 * on first interaction so it works identically for a 2-person direct thread
 * and a 10k-member channel. Unread counts are pointer-based (messages with id
 * greater than last_read_message_id), never per-message receipt rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_user_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_user_states');
    }
};
