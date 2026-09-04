<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The grades a subject is taught in — an EXPLICIT set, not a from/to range
 * (real curricula have gaps: ICT in selected grades, streams in preparatory).
 * No rows for a subject = open (applies to every grade). The subject's other
 * settings (load, room type, category) travel with the whole set.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('grade_level_subject', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();

            $table->unique(['grade_level_id', 'subject_id']);
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_level_subject');
    }
};
