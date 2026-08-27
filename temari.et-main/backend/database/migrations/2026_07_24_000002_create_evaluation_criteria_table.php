<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One rubric line of an appraisal template: a domain (planning, teaching,
 * ethics…), a criterion label, its weight share (all weights sum to 100) and
 * the rating scale ceiling (out of 5 by default). Replaced wholesale when the
 * template is edited — history lives in evaluation_scores snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_template_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 64);
            $table->string('label');
            $table->decimal('weight', 5, 2);
            $table->decimal('max_score', 5, 2)->default(5);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['evaluation_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
