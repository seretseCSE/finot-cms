<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grades a fee applies to. No rows = the fee applies to every grade.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('fee_structure_grade_level', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_level_id')->constrained()->cascadeOnDelete();

            $table->unique(['fee_structure_id', 'grade_level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_grade_level');
    }
};
