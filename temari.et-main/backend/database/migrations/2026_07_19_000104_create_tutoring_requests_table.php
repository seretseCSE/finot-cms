<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A hire request from a guardian (for a child) or an adult learner (for
 * themselves) to one tutor. Accepting creates the engagement with the
 * agreed terms; the request archives the negotiation.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutoring_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            // The learner: a linked student (child) or the requester themself.
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('subject_ids')->nullable(); // wished subjects (display)
            $table->string('grade_label', 40)->nullable();
            $table->text('message')->nullable();
            $table->string('mode', 12)->default('online');
            $table->smallInteger('sessions_per_week')->default(2);
            $table->decimal('hours_per_session', 4, 2)->default(1);
            $table->string('status', 12)->default('pending'); // TutoringRequestStatus
            $table->timestamp('responded_at')->nullable();
            $table->string('response_note')->nullable();
            $table->timestamps();

            $table->index(['tutor_profile_id', 'status']);
            $table->index(['requester_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutoring_requests');
    }
};
