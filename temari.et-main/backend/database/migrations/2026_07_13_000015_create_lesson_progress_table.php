<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One user's state on one lesson (started → completed). Keyed on user_id —
 * no-school B2C learners are first-class (ADR-016); `course_id` is
 * denormalised so course rollups are one grouped count.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('started');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_lesson_id']);
            $table->index(['user_id', 'course_id']);
            // Course-wide progress stats / unpublish guard query by course alone.
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
