<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A non-working day on the school calendar. branch_id null = every branch of
 * the school. Ethiopian public holidays (Meskel, Eid, Fasika…) shift on the
 * Gregorian calendar, so each year's dates are entered as plain rows. Feeds
 * the staff-attendance roster and the leave working-day calculation.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
