<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Learning materials (ADR-016). One row of truth per material — never
 * per-section copies. Audience: teacher posts target specific classes
 * (course_material_targets rows); director/principal posts land subject +
 * grade-window wide for the branch/school (no target rows); platform rows
 * (`school_id` null) feed the exam-prep library. v1 video = YouTube embed or
 * R2 file (signed URLs) — native streaming is a later project.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('min_grade_sort')->nullable();
            $table->unsignedSmallInteger('max_grade_sort')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            // file | link | youtube | text — `content` is shaped by the type
            // ({path,size,mime} | {url} | {video_id} | {body}).
            $table->string('type', 10);
            $table->jsonb('content');
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['school_id', 'branch_id', 'is_active']);
            $table->index('subject_id');
        });

        // Global search: staff find LMS content by title from the palette.
        DB::statement('CREATE INDEX course_materials_title_trgm ON course_materials USING gin (title gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
