<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * @-mentions extracted from the inline @[user:123] tokens at send time.
 * Drives the "mentioned you" notification (which pierces a conversation
 * mute, never the user's master switches) and a future mentions inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['chat_message_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_mentions');
    }
};
