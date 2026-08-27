<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The bell schedule: one row per daily time block of a term, in order —
 * teaching periods AND the gaps between them (break/lunch/flag ceremony).
 * Class rows carry `period_number` (1..n); timetable slots reference that
 * number and derive their times from HERE, so editing the bell schedule once
 * re-times every slot. Double periods must never straddle a non-class row —
 * the solver and the validator both read this table for that rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('term_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence'); // display order within the day
            $table->string('type', 10)->default('class'); // class|break|lunch|flag
            // Set on class rows only: the number timetable slots point at.
            $table->unsignedTinyInteger('period_number')->nullable();
            $table->string('label', 50)->nullable(); // "Break", "ሰንደቅ ዓላማ"…
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['term_id', 'sequence']);
            $table->unique(['term_id', 'period_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_periods');
    }
};
