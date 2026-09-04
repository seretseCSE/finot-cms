<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class targeting for teacher-posted materials: which subject_assignments
 * (classes) a material is shared with. Absence of rows on a school material
 * means "whole grade window" (director/principal posts).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('course_material_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_assignment_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_material_id', 'subject_assignment_id'], 'material_target_unique');
            $table->index('subject_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_material_targets');
    }
};
