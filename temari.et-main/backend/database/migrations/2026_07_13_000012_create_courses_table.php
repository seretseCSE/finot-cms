<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A structured course (modules → lessons → progress) served by ONE engine to
 * three audiences: platform courses (school_id null — the EUEE/exam-prep
 * catalog, open to any authenticated user once published), school courses
 * (school_id set, branch_id optionally narrowing, grade window filtering),
 * and class courses (subject_assignment_id set — that class only).
 * `is_sequential` enforces Canvas-style ordered completion. Streams matter
 * for grade 11–12 prep content (natural | social | null = everyone).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_assignment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('min_grade_sort')->nullable();
            $table->unsignedSmallInteger('max_grade_sort')->nullable();
            $table->string('stream', 10)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('language', 5)->default('en');
            $table->string('cover_path', 2048)->nullable();
            $table->boolean('is_sequential')->default(false);
            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['subject_id', 'status']);
        });

        // Global search: staff find LMS content by title from the palette.
        DB::statement('CREATE INDEX courses_title_trgm ON courses USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
