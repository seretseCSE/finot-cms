<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student's health conditions (catalog pivot) with per-condition severity,
 * notes and medication. Sensitive: serialized only on the student DETAIL
 * endpoint, never in list payloads.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('student_health_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('health_condition_id')->constrained()->restrictOnDelete();
            $table->string('severity', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('medication')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'health_condition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_health_conditions');
    }
};
