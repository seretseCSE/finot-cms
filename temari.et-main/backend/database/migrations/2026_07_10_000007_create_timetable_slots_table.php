<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One scheduled lesson cell: (version, day, period) × subject assignment.
 * Row-per-slot — never weekday columns (School-X anti-pattern). Times are NOT
 * stored here: they derive from the term's bell schedule (term_periods), so
 * re-timing the day never touches slots. `room_id` is set only when the
 * lesson leaves the section's home room (lab/gym…). `is_locked` pins the
 * slot through solver regenerations.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('timetable_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('timetable_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1=Monday … 6=Saturday
            $table->unsignedTinyInteger('period_number');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(
                ['timetable_version_id', 'subject_assignment_id', 'day_of_week', 'period_number'],
                'timetable_slots_unique_cell',
            );
            $table->index(['timetable_version_id', 'day_of_week', 'period_number'], 'timetable_slots_grid');
            $table->index('subject_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_slots');
    }
};
