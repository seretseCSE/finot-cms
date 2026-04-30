<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->enum('phase', ['upcoming', 'current', 'completed'])->nullable()->after('status');
        });

        // Backfill: Active -> current, others -> completed
        DB::table('academic_years')
            ->where('status', 'Active')
            ->update(['phase' => 'current']);

        DB::table('academic_years')
            ->whereNull('phase')
            ->update(['phase' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropColumn('phase');
        });
    }
};
