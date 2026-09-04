<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temari's metadata over the Laravel AI SDK conversation store: one row per
 * chat session in the /ai surface. The SDK's `agent_conversations` +
 * `agent_conversation_messages` tables (its own published migration) hold the
 * transcript and drive model memory; this table owns everything the UI and
 * the authorization model need — the lane the session was opened in, the
 * school/branch context it is pinned to (a leadership chat opened at School A
 * must never answer with School B data after a context switch), the child a
 * parent session focuses on, and list-surface state (title, pin, ordering).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            // The SDK conversation UUID (agent_conversations.id).
            $table->string('uuid', 36);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // AiLane enum value: student|parent|teacher|leadership|registrar|finance|platform.
            $table->string('lane', 20);
            // Staff lanes freeze the workspace the chat was opened in.
            $table->foreignId('school_id')->nullable()->constrained();
            $table->foreignId('branch_id')->nullable()->constrained();
            // Parent lane: the linked child this session focuses on (optional).
            $table->foreignId('student_id')->nullable()->constrained();
            $table->string('title');
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'last_message_at']);
        });

        // Partial unique per the soft-delete rule: a trashed session never
        // blocks the UUID (the SDK row is reused only by restore, never by a
        // new session, but the invariant stays cheap to hold).
        DB::statement('CREATE UNIQUE INDEX ai_conversations_uuid_unique ON ai_conversations (uuid) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
