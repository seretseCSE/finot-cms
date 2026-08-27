<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On-behalf mark entry (the marklist trust rule): a supervisor may only type
 * into a teacher-owned draft after explicitly declaring assistance — who,
 * when and why are recorded here and surfaced on the grid, at submission and
 * in the approval queue. Per-cell authorship stays on
 * `assessment_results.recorded_by`; these fields carry the declaration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marklists', function (Blueprint $table) {
            $table->foreignId('assisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assisted_at')->nullable();
            $table->string('assist_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marklists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assisted_by');
            $table->dropColumn(['assisted_at', 'assist_reason']);
        });
    }
};
