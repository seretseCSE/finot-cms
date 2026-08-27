<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed question picks for a quiz (used when quizzes.draw is null).
 * `points` overrides the question's default weight inside this quiz.
 * Questions referenced here are retired, never deleted (restrict).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->decimal('points', 6, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Index into quizzes.parts; null = not filed under a part.
            $table->unsignedSmallInteger('part_index')->nullable();
            $table->timestamps();

            $table->unique(['quiz_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
