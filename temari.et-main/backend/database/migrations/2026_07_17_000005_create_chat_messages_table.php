<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Messages are official records: soft-deleted rows render as "message
 * removed" (the moderation/audit lane still sees originals), edits stamp
 * edited_at, and the communication-book approval gate parks teacher→parent
 * messages as status 'pending' — INVISIBLE to the family until a director
 * approves (status 'sent') or rejects with a note back to the teacher.
 *
 *  - kind: text | voice | system (system rows are i18n event markers —
 *    "X joined", "approved by Y" — body holds the event key, meta the params)
 *  - attachments: JSONB array of {name, path, size, mime_type} (R2 keys,
 *    served via signed URLs; same shape as assignment submissions)
 *  - meta: voice duration, emergency flag, system params
 *  - client_uuid: idempotency for offline/3G retries — the PWA outbox can
 *    resend without duplicating
 *  - mentions ride inline as @[user:123] tokens; chat_message_mentions
 *    indexes them for "mentioned you" notifications
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12)->default('text'); // text | voice | system
            $table->text('body')->nullable();
            $table->jsonb('attachments')->nullable();
            $table->jsonb('meta')->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('chat_messages')->nullOnDelete();

            // Communication-book gate: sent | pending | rejected.
            $table->string('status', 12)->default('sent');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();

            // Pinned messages: surfaced in a bar at the top of the thread
            // (Telegram-style, multiple allowed). Managed by moderators /
            // group owners / channel announcers / either side of a direct.
            $table->timestamp('pinned_at')->nullable();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->uuid('client_uuid')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'id']);
            $table->index(['status', 'conversation_id']);
            $table->index(['conversation_id', 'pinned_at']);
            $table->unique(['conversation_id', 'user_id', 'client_uuid']);

            // In-chat message search haystack (author-scoped at query time).
            $table->text('search_text')->storedAs("coalesce(body, '')");
        });

        DB::statement('CREATE INDEX chat_messages_search_text_trgm ON chat_messages USING gin (search_text gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
