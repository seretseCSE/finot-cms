<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tutoring contract: tutor × learner with SNAPSHOTTED terms (hourly
 * rate, commission percent, schedule) — later profile/policy changes never
 * rewrite a running contract; only a new agreement does. `payer_user_id`
 * is who funds the monthly cycles (guardian for a child, the learner for
 * an adult). The chat thread is an ADR-019 context conversation.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutoring_engagements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('payer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('request_id')->nullable()->constrained('tutoring_requests')->nullOnDelete();
            $table->jsonb('subjects')->nullable(); // display snapshot [{id,name}]
            $table->string('grade_label', 40)->nullable();
            $table->string('mode', 12)->default('online');
            $table->smallInteger('sessions_per_week')->default(2);
            $table->decimal('hours_per_session', 4, 2)->default(1);
            $table->decimal('hourly_rate', 8, 2); // snapshot
            $table->decimal('commission_percent', 5, 2); // snapshot
            $table->string('status', 12)->default('active'); // EngagementStatus
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tutor_profile_id', 'status']);
            $table->index(['payer_user_id', 'status']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutoring_engagements');
    }
};
