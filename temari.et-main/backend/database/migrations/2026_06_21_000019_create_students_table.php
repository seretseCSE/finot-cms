<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permanent student identity — a global PERSON, not a tenant record (ADR-011).
 * `school_id`/`branch_id` are nullable REGISTRATION PROVENANCE only (the branch
 * that first registered the student); B2C students (exam prep, tutoring) have
 * neither. WHERE a student currently studies lives exclusively in
 * `student_enrollments` — never grant or scope access off these columns alone.
 *
 * Names use the Ethiopian patronymic convention. `user_id` is null for young
 * children with no login. Fayda national IDs are stored hashed only.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();

            $table->string('first_name');
            $table->string('father_name');
            $table->string('grandfather_name')->nullable();
            $table->string('mother_name')->nullable();

            $table->string('gender');
            $table->date('date_of_birth')->nullable();
            $table->string('national_student_id')->nullable();
            $table->string('fayda_hash')->nullable();
            $table->string('primary_phone')->nullable();

            // Public-facing person code (H8R6WV style) — students often have no
            // user account, so they carry their own. Uppercase, unambiguous
            // alphabet; assigned in the model's creating hook.
            $table->char('public_id', 6)->nullable()->unique();

            $table->string('email')->nullable();
            $table->string('citizenship')->default('Ethiopian');
            $table->string('marital_status', 20)->nullable();
            $table->string('photo_path')->nullable();

            // Home languages (codes from App\Support\Languages) — most students
            // speak Amharic, hence the default.
            $table->jsonb('languages')->default('["am"]');

            // Health profile. The condition list lives on the
            // student_health_conditions pivot; these are the loose ends.
            $table->string('blood_type', 5)->nullable();
            $table->text('health_notes')->nullable();

            // Birthplace (where they were born) and current address (where they
            // live) — same field convention as employees/branches.
            $table->string('birth_country')->nullable();
            $table->string('birth_state')->nullable();
            $table->string('birth_city')->nullable();
            $table->string('birth_sub_city')->nullable();
            $table->string('birth_woreda')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('sub_city')->nullable();
            $table->string('woreda')->nullable();
            $table->string('house_no')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'branch_id']);
            // /me lane: every family request resolves the student by user_id.
            $table->index('user_id');

            // One flat haystack for the global (⌘K) search: every way a human
            // identifies a student — names, phone, email, public/national IDs.
            // Generated so it can never drift from the source columns.
            $table->text('search_text')->storedAs(
                "first_name || ' ' || father_name"
                ." || ' ' || coalesce(grandfather_name, '')"
                ." || ' ' || coalesce(mother_name, '')"
                ." || ' ' || coalesce(primary_phone, '')"
                // Digits-only phone variant so a query typed without the
                // stored separators ("0911223344" vs "0911-22-33-44") hits.
                ." || ' ' || coalesce(regexp_replace(primary_phone, '\\D', '', 'g'), '')"
                ." || ' ' || coalesce(email, '')"
                ." || ' ' || coalesce(public_id, '')"
                ." || ' ' || coalesce(national_student_id, '')",
            );
        });

        // Trigram index so partial, case-insensitive matches stay index-backed
        // at scale (pg_trgm is enabled in the users migration).
        DB::statement('CREATE INDEX students_search_text_trgm ON students USING gin (search_text gin_trgm_ops)');

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index students_national_student_id_unique'
            .' on students (national_student_id) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
