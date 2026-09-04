<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tutor marketplace identity (Upwork-model public profile). One per
 * user; relationship-lane access derives from THIS row (ADR-012 — tutors
 * hold no memberships). Only `approved` profiles are publicly listed.
 *
 * Fayda: encrypted at rest (staff reviewers must READ it to vet documents
 * until the Fayda API lands) + a hash for duplicate detection — the tutor
 * exception to the students' hash-only rule, agreed 2026-07-19.
 *
 * Money denormalizations (rating_avg/count, hours_taught, wallet_balance)
 * are maintained by their single writers (TutorRating / the ledger) — never
 * computed in list queries.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->nullable(); // SEO handle, set on approval
            $table->string('headline', 120)->nullable();
            $table->text('bio')->nullable();
            $table->string('video_url')->nullable();
            $table->decimal('hourly_rate', 8, 2)->nullable();
            // Optional discounted rate for each additional sibling in the
            // same engagement (Ethiopian home-tutoring reality).
            $table->decimal('additional_child_rate', 8, 2)->nullable();
            $table->string('mode', 12)->default('both'); // online | in_person | both
            $table->string('region', 80)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('sub_city', 80)->nullable();
            $table->jsonb('languages')->nullable(); // ['am','en','om',…]
            $table->string('education_level', 60)->nullable();
            $table->smallInteger('experience_years')->nullable();

            // Fayda national ID: encrypted (reviewer-readable) + hash (dupes).
            $table->text('fayda_id')->nullable();
            $table->string('fayda_hash')->nullable();

            $table->string('status', 12)->default('draft'); // TutorStatus
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decline_reason')->nullable();
            $table->string('suspend_reason')->nullable();

            // Payout account (Chapa transfer target), tutor-managed.
            $table->string('payout_bank_code', 20)->nullable();
            $table->string('payout_bank_name', 80)->nullable();
            $table->string('payout_account_number', 40)->nullable();
            $table->string('payout_account_name', 120)->nullable();

            // Per-tutor commission override; null = platform default.
            $table->decimal('commission_percent', 5, 2)->nullable();

            // Maintained aggregates (single writers only).
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('rating_count')->default(0);
            $table->decimal('hours_taught', 8, 1)->default(0);
            $table->unsignedInteger('students_count')->default(0);
            $table->decimal('wallet_balance', 12, 2)->default(0);

            $table->timestamp('boosted_until')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'boosted_until']);
            $table->index(['status', 'hourly_rate']);
            $table->index(['status', 'rating_avg']);
        });

        // Partial uniques (soft-deleting table): one live profile per user,
        // one live claim per Fayda identity, one live slug.
        DB::statement('CREATE UNIQUE INDEX tutor_profiles_user_id_unique ON tutor_profiles (user_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX tutor_profiles_fayda_hash_unique ON tutor_profiles (fayda_hash) WHERE deleted_at IS NULL AND fayda_hash IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX tutor_profiles_slug_unique ON tutor_profiles (slug) WHERE deleted_at IS NULL AND slug IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_profiles');
    }
};
