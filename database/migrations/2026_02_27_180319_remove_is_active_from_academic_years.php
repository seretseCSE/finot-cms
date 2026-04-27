<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite: Need to recreate table without is_active column
            $this->recreateTableForSQLite();
        } else {
            // MySQL/PostgreSQL: Use standard approach
            $this->migrateStandard();
        }
    }

    /**
     * Handle standard migration for MySQL/PostgreSQL
     */
    protected function migrateStandard(): void
    {
        // Drop the functional index if it exists
        try {
            DB::statement('DROP INDEX academic_years_one_active ON academic_years');
        } catch (\Throwable $e) {
            // Ignore if index doesn't exist
        }

        // Drop the standard index
        try {
            Schema::table('academic_years', function (Blueprint $table) {
                $table->dropIndex(['is_active']);
            });
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
     * Recreate table for SQLite without is_active column
     */
    protected function recreateTableForSQLite(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            // Get existing data
            $data = DB::table('academic_years')->get();

            // Drop existing table
            Schema::dropIfExists('academic_years');

            // Recreate table without is_active column (without foreign key constraints for simplicity)
            DB::statement('CREATE TABLE academic_years (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(200) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status VARCHAR(20) DEFAULT \'Draft\' NOT NULL,
                activated_at DATETIME DEFAULT NULL,
                deactivated_at DATETIME DEFAULT NULL,
                activated_by BIGINT DEFAULT NULL,
                deactivated_by BIGINT DEFAULT NULL,
                created_by BIGINT NOT NULL,
                created_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL
            )');

            // Create indexes
            DB::statement('CREATE INDEX academic_years_status_index ON academic_years(status)');

            // Restore data
            foreach ($data as $row) {
                DB::table('academic_years')->insert([
                    'id' => $row->id,
                    'name' => $row->name,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'status' => $row->status ?? 'Draft',
                    'activated_at' => $row->activated_at,
                    'deactivated_at' => $row->deactivated_at,
                    'activated_by' => $row->activated_by,
                    'deactivated_by' => $row->deactivated_by,
                    'created_by' => $row->created_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->recreateTableDownForSQLite();
        } else {
            $this->migrateStandardDown();
        }
    }

    /**
     * Reverse standard migration
     */
    protected function migrateStandardDown(): void
    {
        // Add back the is_active column
        Schema::table('academic_years', function (Blueprint $table) {
            $table->boolean('is_active')->default(false);
        });

        // Drop the old index if it exists
        try {
            DB::statement('DROP INDEX IF EXISTS academic_years_one_active ON academic_years');
        } catch (\Throwable $e) {
            // Ignore if index doesn't exist
        }

        // Add standard index
        Schema::table('academic_years', function (Blueprint $table) {
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse table recreation for SQLite
     */
    protected function recreateTableDownForSQLite(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('PRAGMA foreign_keys = OFF');

        try {
            // Get existing data
            $data = DB::table('academic_years')->get();

            // Drop and recreate with is_active
            Schema::dropIfExists('academic_years');

            DB::statement('CREATE TABLE academic_years (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(200) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                is_active BOOLEAN DEFAULT 0 NOT NULL,
                status VARCHAR(20) DEFAULT \'Draft\' NOT NULL,
                activated_at DATETIME DEFAULT NULL,
                deactivated_at DATETIME DEFAULT NULL,
                activated_by BIGINT DEFAULT NULL,
                deactivated_by BIGINT DEFAULT NULL,
                created_by BIGINT NOT NULL,
                created_at DATETIME DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL
            )');

            // Create indexes
            DB::statement('CREATE INDEX academic_years_status_index ON academic_years(status)');
            DB::statement('CREATE INDEX academic_years_is_active_index ON academic_years(is_active)');

            // Restore data
            foreach ($data as $row) {
                DB::table('academic_years')->insert([
                    'id' => $row->id,
                    'name' => $row->name,
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'status' => $row->status ?? 'Draft',
                    'is_active' => ($row->status === 'Active') ? 1 : 0,
                    'activated_at' => $row->activated_at,
                    'deactivated_at' => $row->deactivated_at,
                    'activated_by' => $row->activated_by,
                    'deactivated_by' => $row->deactivated_by,
                    'created_by' => $row->created_by,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        } finally {
            // Re-enable foreign key checks
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }
};
