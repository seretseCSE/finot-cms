<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class () extends AiMigration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('ai.conversations.tables.conversations', 'agent_conversations'), function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignId('user_id')->nullable();
            $table->string('title');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('ai.conversations.tables.conversations', 'agent_conversations'));
    }
};
