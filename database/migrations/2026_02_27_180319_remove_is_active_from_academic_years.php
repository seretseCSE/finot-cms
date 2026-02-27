<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old unique index that used is_active
        try {
            DB::statement('DROP INDEX academic_years_one_active ON academic_years');
        } catch (\Throwable $e) {
            // Ignore if index doesn't exist
        }

        // Remove the is_active column
        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        // Create a new unique index using only status
        try {
            DB::statement('CREATE UNIQUE INDEX academic_years_one_active ON academic_years ((CASE WHEN status = "Active" THEN 1 ELSE NULL END))');
        } catch (\Throwable $e) {
            // Fallback to application-level enforcement
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the is_active column
        Schema::table('academic_years', function (Blueprint $table) {
            $table->boolean('is_active')->default(false);
        });

        // Set is_active based on status
        DB::table('academic_years')->update([
            'is_active' => DB::raw('CASE WHEN status = "Active" THEN 1 ELSE 0 END')
        ]);

        // Drop the new index
        try {
            DB::statement('DROP INDEX academic_years_one_active ON academic_years');
        } catch (\Throwable $e) {
            // Ignore if index doesn't exist
        }

        // Recreate the old index
        try {
            DB::statement('CREATE UNIQUE INDEX academic_years_one_active ON academic_years ((CASE WHEN is_active = 1 THEN 1 ELSE NULL END))');
        } catch (\Throwable $e) {
            // Fallback to application-level enforcement
        }
    }
};
