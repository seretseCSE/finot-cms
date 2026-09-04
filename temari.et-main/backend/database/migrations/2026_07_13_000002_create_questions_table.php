<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One question in a bank (ADR-016). `body` holds the type-shaped content
 * (stem HTML, options, pairs, blanks, attachments); `answer_key` holds
 * correct answers, tolerances and keyword rubrics and NEVER leaves the
 * server for takers. `topic` files the question under one of the bank's
 * chapters; national past-paper rows carry `source` provenance. Referenced
 * questions are retired, never deleted.
 *
 * A `group` row is a parent container (reading passage, matching set):
 * `parent_id` points sub-questions at it and `position` orders them. Groups
 * are one level deep — a child can never itself be a group — and travel as
 * one unit through exam papers (shuffling never separates siblings).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('question_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('questions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('type', 20);
            $table->jsonb('body');
            $table->jsonb('answer_key')->nullable();
            $table->decimal('points', 6, 2)->default(1);
            $table->string('difficulty', 10)->nullable();
            // One of the bank's topics/chapters (free text, bank-curated list).
            $table->string('topic', 120)->nullable();
            $table->jsonb('tags')->nullable();
            // Provenance, e.g. "national:2016:g12:natural" for past papers.
            $table->string('source')->nullable();
            // Shown after grading / in exam-prep review mode — the learning half.
            $table->text('explanation')->nullable();
            $table->string('status', 12)->default('published');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['parent_id', 'position']);
            $table->index(['question_bank_id', 'status']);
            $table->index(['question_bank_id', 'difficulty']);
            $table->index(['question_bank_id', 'topic']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
