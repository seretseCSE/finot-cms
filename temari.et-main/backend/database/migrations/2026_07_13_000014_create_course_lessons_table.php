<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One ordered item inside a module. `type` shapes `content`:
 * video ({url} or {video_id} for YouTube), reading ({body} markdown),
 * file ({path,name,size,mime_type} on R2 behind signed URLs) — and quiz
 * lessons reference the quiz engine (`quiz_id`), never a second player.
 * `course_id` is denormalised for cheap progress rollups.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('course_lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_module_id')->constrained()->cascadeOnDelete();
            // video | reading | file | quiz
            $table->string('type', 12);
            $table->string('title');
            $table->jsonb('content')->nullable();
            $table->foreignId('quiz_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['course_module_id', 'sort_order']);
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
    }
};
