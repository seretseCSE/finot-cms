<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // The in-app notification feed — one row per user per event. Rows
        // store the EVENT KEY + params only (never rendered text): title/body
        // render at read time in the reader's preferred_language, so a user
        // who switches to Amharic re-reads their whole feed in Amharic.
        // Deliberately NOT soft-deleted: the feed is ephemeral delivery state
        // (pruned after 90 days by notifications:prune), not domain history —
        // the audit trail lives in activity_logs and the domain tables.
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Catalog event key, e.g. `finance.invoice_issued` (see
            // App\Support\NotificationCatalog — the single source of truth).
            $table->string('event', 64);
            $table->string('category', 24);
            // Context of the emitting scope — powers deep links + workspace
            // switching on tap. Nullable: platform / relationship-lane events
            // have no tenant scope.
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            // i18n params + deep-link params (student name, amounts, counts…).
            $table->jsonb('data')->default('{}');
            // In-app route the row deep-links to (frontend path, no origin).
            $table->string('link')->nullable();
            // Digest folding: an unread row with the same (user, dedupe_key)
            // is replaced (count bumped) instead of stacking 40 siblings.
            // Postgres treats NULLs as distinct, so rows without a key pass.
            $table->string('dedupe_key', 120)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'dedupe_key']);
            // Feed scan (newest first) and unread badge count.
            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
