<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a teacher is NOT available — the exception list, since full-time
 * availability is the default. A row blocks one weekday, either whole
 * (from/to null) or a period window (e.g. part-timers who only teach
 * mornings, or a standing Tuesday-afternoon commitment). The timetable
 * solver and validator treat these as hard constraints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1=Monday … 6=Saturday
            // Null bounds = the whole day is blocked.
            $table->unsignedTinyInteger('from_period')->nullable();
            $table->unsignedTinyInteger('to_period')->nullable();
            $table->string('note', 120)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_availabilities');
    }
};
