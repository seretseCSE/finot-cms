<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Behavioral/skill checklist ratings for the report card (the school-defined
 * "Academic/Behavioral Assessment" panel): a per-term map of skill key →
 * rating code (E | VG | S | NI). Entered by the homeroom teacher alongside
 * conduct, so — like conduct and the comment — the freeze/recompute never
 * touches it. Display-only on the printed card, never queried relationally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_term_results', function (Blueprint $table): void {
            $table->jsonb('skills')->nullable()->after('conduct');
        });
    }

    public function down(): void
    {
        Schema::table('student_term_results', function (Blueprint $table): void {
            $table->dropColumn('skills');
        });
    }
};
