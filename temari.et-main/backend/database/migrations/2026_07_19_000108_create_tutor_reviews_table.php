<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reviews are earned, never bought: one per RELEASED cycle per direction
 * (the Upwork rule — you can only rate someone you actually paid/taught).
 * family_to_tutor is public (profile aggregate via TutorRating);
 * tutor_to_family stays private.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutor_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('engagement_id')->constrained('tutoring_engagements')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('tutoring_cycles')->cascadeOnDelete();
            $table->foreignId('reviewer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('direction', 20); // family_to_tutor | tutor_to_family
            $table->smallInteger('rating'); // 1..5
            $table->text('comment')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tutor_profile_id', 'direction', 'is_public']);
        });

        DB::statement('CREATE UNIQUE INDEX tutor_reviews_cycle_direction_unique ON tutor_reviews (cycle_id, direction) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_reviews');
    }
};
