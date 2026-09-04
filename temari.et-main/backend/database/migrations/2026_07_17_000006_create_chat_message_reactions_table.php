<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Emoji reactions — one row per user × message × emoji, toggled. In
 * reactions-only announcement channels this is the whole feedback loop
 * (cheap on bandwidth, low-literacy friendly).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('chat_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['chat_message_id', 'user_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_reactions');
    }
};
