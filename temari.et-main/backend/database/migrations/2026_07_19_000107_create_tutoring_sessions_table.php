<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One lesson inside a cycle. The tutor schedules/logs; the family confirms
 * (auto-confirm after Marketplace::AUTO_CONFIRM_HOURS); confirmed hours ×
 * rate is the ONLY value a release pays. Meeting URL = the online room
 * (Jitsi) minted per session.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutoring_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained('tutoring_cycles')->cascadeOnDelete();
            $table->foreignId('engagement_id')->constrained('tutoring_engagements')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->decimal('duration_hours', 4, 2)->default(1);
            $table->string('topic')->nullable();
            $table->string('status', 12)->default('scheduled'); // TutoringSessionStatus
            $table->string('meeting_url')->nullable();
            $table->timestamp('logged_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dispute_reason')->nullable();
            $table->timestamp('disputed_at')->nullable();
            $table->string('resolution', 12)->nullable(); // upheld | rejected
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['cycle_id', 'status']);
            $table->index(['engagement_id', 'scheduled_at']);
            $table->index(['status', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutoring_sessions');
    }
};
