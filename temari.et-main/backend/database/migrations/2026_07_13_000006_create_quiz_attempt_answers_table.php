<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One answer per question per attempt, autosaved as the taker moves — a
 * dropped 3G connection never loses work. Scoring is server-side only:
 * `auto_score` from the AutoGrader, `manual_score` from a human (wins when
 * both are set), `ai_score` reserved for the AI-grading phase.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('quiz_attempt_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->jsonb('answer')->nullable();
            $table->decimal('auto_score', 6, 2)->nullable();
            $table->decimal('manual_score', 6, 2)->nullable();
            $table->decimal('ai_score', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
    }
};
