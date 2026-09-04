<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parent/guardian profile — NOT school-scoped. Access to a school's data is
 * derived from the linked child's active enrollment, never from a membership.
 * The primary phone lives on the linked user (login identifier); this table
 * holds the extended profile. Notification channel preferences live on the
 * users table (they belong to the person, not the parent hat).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('parents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Patronymic name trio — users.name stays the single display name
            // and is kept in sync when the trio is provided.
            $table->string('first_name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('grandfather_name')->nullable();

            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->default('Ethiopia');
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->string('photo_path')->nullable();

            // Current address — same field convention as employees/branches.
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('sub_city')->nullable();
            $table->string('woreda')->nullable();
            $table->string('house_no')->nullable();

            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Flat haystack for the global (⌘K) search — see students migration.
            // Login phone/email/public_id live on the linked user and are
            // searched through the users indexes.
            $table->text('search_text')->storedAs(
                "coalesce(first_name, '')"
                ." || ' ' || coalesce(father_name, '')"
                ." || ' ' || coalesce(grandfather_name, '')"
                ." || ' ' || coalesce(secondary_phone, '')"
                ." || ' ' || coalesce(regexp_replace(secondary_phone, '\\D', '', 'g'), '')",
            );
        });

        DB::statement('CREATE INDEX parents_search_text_trgm ON parents USING gin (search_text gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('parents');
    }
};
