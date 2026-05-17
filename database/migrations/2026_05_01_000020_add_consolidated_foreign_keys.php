<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // FK: member_parent_guardians.parent_id -> parents.id
        if (Schema::hasTable('member_parent_guardians') && Schema::hasTable('parents')) {
            Schema::table('member_parent_guardians', function (Blueprint $table) {
                if (! $this->foreignKeyExists('member_parent_guardians', 'member_parent_guardians_parent_id_foreign')) {
                    $table->foreign('parent_id')->references('id')->on('parents')->onDelete('set null');
                }
            });
        }

        // FK: financial_transactions.bank_account_id -> bank_accounts.id
        if (Schema::hasTable('financial_transactions') && Schema::hasTable('bank_accounts')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                if (! $this->foreignKeyExists('financial_transactions', 'financial_transactions_bank_account_id_foreign')) {
                    $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
                }
            });
        }

        // FK: donations.bank_account_id -> bank_accounts.id
        if (Schema::hasTable('donations') && Schema::hasTable('bank_accounts')) {
            Schema::table('donations', function (Blueprint $table) {
                if (! $this->foreignKeyExists('donations', 'donations_bank_account_id_foreign')) {
                    $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('member_parent_guardians', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
        });
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
        });
    }

    private function foreignKeyExists(string $table, string $fkName): bool
    {
        $conn = Schema::getConnection();

        if ($conn->getDriverName() === 'sqlite') {
            // SQLite does not expose information_schema; check via PRAGMA
            try {
                $fkList = $conn->select("PRAGMA foreign_key_list($table)");
                return collect($fkList)->contains('id', function ($fk) use ($fkName) {
                    // SQLite FK names are auto-generated; match by column name pattern
                    return false;
                });
            } catch (\Exception) {
                return false;
            }
        }

        $database = $conn->getDatabaseName();
        $result = $conn->select(
            "SELECT COUNT(*) as count FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?",
            [$database, $table, $fkName]
        );
        return ($result[0]->count ?? 0) > 0;
    }
};
