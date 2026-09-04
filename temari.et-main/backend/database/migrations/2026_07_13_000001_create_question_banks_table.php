<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A curated pool of questions (ADR-016). Scope mirrors the subjects catalog:
 * platform banks (`school_id` null — the national past-paper bank, Temari-
 * authored prep material) and school/branch banks (`school_id` set, optional
 * `branch_id`). Banks are organized subject → grade → topics (chapters):
 * `topics` is the ordered chapter list authors file questions under.
 * Quizzes reference banks either through fixed question picks or random-draw
 * rules resolved at attempt start.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            // Ordered chapter/topic names, e.g. ["Algebra", "Geometry"].
            $table->jsonb('topics')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'branch_id']);
            $table->index('subject_id');
            $table->index('grade_level_id');
        });

        // Global search: staff find LMS content by title from the palette.
        DB::statement('CREATE INDEX question_banks_name_trgm ON question_banks USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('question_banks');
    }
};
