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
        // Drop index first so SQLite can drop the column (MySQL handles this implicitly)
        $indexes = Schema::getIndexes('contributions');
        $hasStatusIndex = collect($indexes)->contains('name', 'contributions_status_index');
        if ($hasStatusIndex) {
            Schema::table('contributions', function (Blueprint $table) {
                $table->dropIndex('contributions_status_index');
            });
        }

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->string('status')->nullable()->after('amount');
        });
    }
};
