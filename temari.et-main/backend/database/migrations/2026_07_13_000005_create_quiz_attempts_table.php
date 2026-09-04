<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One sitting of a quiz (ADR-016). Attempts hang off `user_id` so no-school
 * B2C takers are first-class; `student_id`/`student_enrollment_id` are set
 * for school takers (reporting + gradebook sync). Security is server-side:
 * `deadline_at` is computed at start and is the only clock that counts,
 * `question_ids` freezes this sitting's resolved+shuffled paper so resume is
 * stable, `token_hash` binds the sitting to the starting session, and
 * `integrity_log` accumulates review flags (blur/paste/second device) —
 * flags are surfaced to humans, never auto-fail.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 12)->default('in_progress');
            $table->timestamp('started_at');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->unsignedInteger('seed');
            $table->jsonb('question_ids');
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->default(0);
            $table->boolean('pending_manual')->default(false);
            $table->string('token_hash')->nullable();
            $table->jsonb('integrity_log')->nullable();
            $table->unsignedSmallInteger('flag_count')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['quiz_id', 'user_id', 'attempt_number']);
            $table->index(['quiz_id', 'status']);
            $table->index('user_id');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
