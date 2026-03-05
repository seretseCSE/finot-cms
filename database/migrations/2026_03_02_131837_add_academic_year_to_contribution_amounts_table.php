<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contribution_amounts', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->onDelete('cascade');
            $table->dropUnique('unique_group_month_effective');
            $table->unique(['group_id', 'month_name', 'academic_year_id'], 'unique_group_month_academic_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contribution_amounts', function (Blueprint $table) {
            $table->dropUnique('unique_group_month_academic_year');
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn('academic_year_id');
            $table->unique(['group_id', 'month_name', 'effective_from'], 'unique_group_month_effective');
        });
    }
};
