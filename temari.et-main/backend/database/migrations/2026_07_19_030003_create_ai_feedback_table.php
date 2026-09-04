<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thumbs up/down on individual assistant messages. `message_id` references
 * the SDK's agent_conversation_messages.id (uuid); one verdict per user per
 * message, updatable in place (switching 👍 → 👎 overwrites).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ai_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('message_id', 36);
            $table->string('rating', 10); // up | down
            $table->string('comment', 1000)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_feedback');
    }
};
