<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One band of a grading scale: a contiguous score range and how it is
 * displayed (letter + label), valued (grade points) and judged (passing).
 * Bands never overlap within a scale (validated at write time); a score
 * resolves to the band whose [min_score, max_score] contains it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scale_bands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('grading_scale_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            // "A", "B+", "E", "VG"… — null on purely numeric scales.
            $table->string('letter', 8)->nullable();
            // "Excellent", "Very Good"… — shown on report cards.
            $table->string('label', 60);
            $table->decimal('grade_points', 3, 2)->nullable();
            $table->boolean('is_passing')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('grading_scale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scale_bands');
    }
};
