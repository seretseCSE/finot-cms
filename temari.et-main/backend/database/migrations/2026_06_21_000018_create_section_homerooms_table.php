<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homeroom teacher per section × academic year — it changes every year, so it
 * is never a column on `sections`. The employee must hold an active teacher
 * position (validated at the request layer).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('section_homerooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'academic_year_id']);
            $table->index(['employee_id', 'academic_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_homerooms');
    }
};
