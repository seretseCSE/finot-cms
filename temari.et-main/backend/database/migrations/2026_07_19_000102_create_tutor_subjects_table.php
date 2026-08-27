<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a tutor teaches: platform-catalog subjects with an EXPLICIT grade set
 * per row (grade sort_orders, same vocabulary as grade_level_subject; empty
 * array = every grade the subject applies to). Drives directory filtering.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->jsonb('grade_sorts')->nullable(); // [] / null = all applicable grades
            $table->timestamps();

            $table->unique(['tutor_profile_id', 'subject_id']);
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_subjects');
    }
};
