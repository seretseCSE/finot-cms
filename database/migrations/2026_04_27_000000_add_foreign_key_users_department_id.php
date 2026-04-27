<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'department_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Drop existing foreign key if it exists (to avoid duplicates on re-run)
            $existing = $this->getExistingForeignKey('users', 'department_id');
            if ($existing) {
                $table->dropForeign($existing);
            }

            $table->foreign('department_id')
                ->references('id')
                ->on('departments')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'department_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $existing = $this->getExistingForeignKey('users', 'department_id');
            if ($existing) {
                $table->dropForeign($existing);
            }
        });
    }

    /**
     * Get the existing foreign key constraint name for a column.
     */
    private function getExistingForeignKey(string $table, string $column): ?string
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();

        if ($driver === 'sqlite') {
            $result = $conn->select(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
                [$table]
            );

            if (empty($result)) {
                return null;
            }

            $sql = $result[0]->sql ?? '';
            // Match foreign key constraint name patterns like: CONSTRAINT xxx FOREIGN KEY (column)
            if (preg_match('/CONSTRAINT\s+[`"\']?([^`"\'\s]+)[`"\']?\s+FOREIGN\s+KEY\s+\([^)]*'.preg_quote($column, '/').'[^)]*\)/i', $sql, $matches)) {
                return $matches[1];
            }

            return null;
        }

        $dbName = $conn->getDatabaseName();

        $result = $conn->select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$dbName, $table, $column]
        );

        return $result[0]->CONSTRAINT_NAME ?? null;
    }
};
