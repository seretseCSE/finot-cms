<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sections a class quiz/exam is given to (ADR-016). One exam paper can
 * run across several sections of the same grade + subject — one row per
 * targeted subject_assignment. `quizzes.subject_assignment_id` stays the
 * ANCHOR (the first/owning class: ownership, term gate, gradebook slot);
 * the target rows ALWAYS include the anchor. Platform mocks have no rows.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('quiz_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_assignment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['quiz_id', 'subject_assignment_id']);
            $table->index('subject_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_targets');
    }
};
