<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One classroom sitting of a daily lesson plan: section × date × period
 * (period numbers reference the term's period schedule, mirrored from the
 * published timetable when one exists). Coverage is marked HERE, per
 * sitting — the same lesson can be covered in 9A and missed in 9B — and is
 * what the pacing gate, carryover and the director's dashboard sum over.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('daily_plan_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('daily_lesson_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->date('teaches_on');
            // Period number in the term's period schedule; null when the
            // branch has no published timetable to pin to.
            $table->unsignedTinyInteger('period_number')->nullable();
            $table->string('coverage', 12)->default('pending');
            $table->string('coverage_note', 500)->nullable();
            $table->timestamps();

            $table->index(['daily_lesson_plan_id']);
            $table->index(['section_id', 'teaches_on']);
            $table->index('teaches_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_plan_deliveries');
    }
};
