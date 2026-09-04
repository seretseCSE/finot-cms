<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A user is a global PERSON — deliberately carrying no school/branch columns.
 * Where they hold roles lives exclusively in `memberships`; what they are to a
 * student lives in `student_guardians`/`students` (ADR-010/011).
 *
 * Temari.et specifics: PHONE is the primary login identifier; email/password
 * are optional (accounts are provisioned, then the person sets their own
 * password via an SMS link). Phone itself is NULLABLE for exactly one case —
 * a student without their own phone, whose account signs in by Temari student
 * ID + PIN and whose SMS (setup link, PIN reset OTP) route to the primary
 * guardian. Global account access is governed by `status`
 * (active|inactive|banned), managed only by platform staff; per-scope access
 * lives on `memberships.is_active`.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            // Public-facing person code (e.g. H8R6WV) — never the DB id. Stored
            // uppercase; searched case-insensitively. Assigned in the model's
            // creating hook via App\Support\PublicId.
            $table->char('public_id', 6)->nullable()->unique();
            $table->string('name');
            $table->string('phone')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('preferred_language', 5)->default('en');

            // Notification channel preferences (user-editable via /me/preferences).
            // Per-child SMS gating for guardians stays on student_guardians.can_receive_sms.
            $table->boolean('notify_via_sms')->default(true);
            $table->boolean('notify_via_email')->default(true);
            $table->boolean('notify_via_push')->default(true);
            // Per-CATEGORY channel overrides (deltas from NotificationCatalog
            // defaults), e.g. {"lms": {"email": false}}. The booleans above
            // stay the master switches; critical events ignore category mutes.
            $table->jsonb('notification_preferences')->nullable();

            // Global account status + audit trail.
            $table->string('status')->default('active');
            $table->timestamp('status_changed_at')->nullable();
            $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_reason')->nullable();

            // Profile + activity.
            $table->string('avatar_path')->nullable();
            $table->timestamp('last_login_at')->nullable();

            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('last_login_at');
            $table->index('created_at');

            // Flat haystack for the global (⌘K) search — one indexed column
            // covering every way a person is identified. The per-column trgm
            // indexes below stay for the Users list's column-scoped search.
            $table->text('search_text')->storedAs(
                "name || ' ' || coalesce(phone, '')"
                ." || ' ' || regexp_replace(coalesce(phone, ''), '\\D', '', 'g')"
                ." || ' ' || coalesce(email, '')"
                ." || ' ' || coalesce(public_id, '')",
            );
        });

        // Trigram indexes for fast case-insensitive partial search at scale.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX users_name_trgm ON users USING gin (name gin_trgm_ops)');
        DB::statement('CREATE INDEX users_phone_trgm ON users USING gin (phone gin_trgm_ops)');
        DB::statement('CREATE INDEX users_email_trgm ON users USING gin (email gin_trgm_ops)');
        DB::statement('CREATE INDEX users_search_text_trgm ON users USING gin (search_text gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
