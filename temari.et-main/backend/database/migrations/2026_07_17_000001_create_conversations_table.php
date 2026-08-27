<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ONE conversation engine for every chat surface (ADR-019): direct messages,
 * ad-hoc groups, audience-rule channels (classroom / branch / school-wide /
 * department) and context threads attached to a domain object (an assignment,
 * a transfer request…). Four kinds:
 *
 *  - direct:  a private thread. Parent↔staff directs ALWAYS carry student_id
 *             ("Ms. Tigist ↔ the guardians of Amanuel") — the thread is about
 *             a child, and every active guardian of that child shares it.
 *             `direct_key` dedupes: the same pair never gets two threads.
 *  - group:   explicit member list (conversation_participants).
 *  - channel: membership is DERIVED at read time from conversation_targets ×
 *             live enrollments/memberships — a 10,000-student school-wide
 *             channel materializes NO participant rows, and transfers/position
 *             changes update access automatically.
 *  - context: attached to context_type/context_id (e.g. assignment × student),
 *             explicit participants like a group.
 *
 * The conversation is tenant-anchored (school_id + branch_id; branch NULL =
 * school-wide channel). settings: posting ('all'|'admins'), reactions_only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            // school NULL = a platform-level thread (tutoring marketplace
            // context conversations have no school tenant).
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 16); // direct | group | channel | context
            $table->string('title')->nullable();

            // channel provisioning anchors (classroom channels re-resolve on
            // year rollover through targets; section_id here is the label).
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();

            // The "regarding" child of a parent↔staff direct thread.
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();

            // Context threads: the domain object the chat hangs off.
            $table->string('context_type', 40)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();

            // Dedupe key for direct threads (see Conversation::directKeyFor).
            $table->string('direct_key')->nullable()->unique();

            // Idempotency key for auto-provisioned system channels
            // ("branch:3:staff_room", "classroom:41", "school:1:announcements").
            $table->string('system_key')->nullable()->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('settings')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'branch_id', 'kind']);
            $table->index(['context_type', 'context_id']);
            $table->index('student_id');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
