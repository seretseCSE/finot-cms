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
        if (! Schema::hasTable('contributions')) {
            Schema::create('contributions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->constrained()->onDelete('cascade');
                $table->foreignId('academic_year_id')->constrained()->onDelete('cascade');
                $table->unsignedTinyInteger('month'); // 1-12
                $table->boolean('is_paid')->default(false);
                $table->decimal('amount', 10, 2);
                $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->unique(['member_id', 'academic_year_id', 'month'], 'contributions_member_year_month_unique');
            });

            return;
        }

        Schema::table('contributions', function (Blueprint $table) {
            if (! Schema::hasColumn('contributions', 'month')) {
                $table->unsignedTinyInteger('month')->nullable()->after('academic_year_id');
            }

            if (! Schema::hasColumn('contributions', 'is_paid')) {
                $table->boolean('is_paid')->default(false)->after('month');
            }
        });

        // Check if index exists (SQLite-compatible approach)
        $driver = DB::getDriverName();
        $indexExists = false;

        if ($driver === 'sqlite') {
            // SQLite: Check using PRAGMA
            $result = DB::select("PRAGMA index_list('contributions')");
            foreach ($result as $row) {
                if ($row->name === 'contributions_member_year_month_unique') {
                    $indexExists = true;
                    break;
                }
            }
        } else {
            // MySQL: Use information_schema
            $indexExists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'contributions')
                ->where('index_name', 'contributions_member_year_month_unique')
                ->exists();
        }

        if (! $indexExists) {
            Schema::table('contributions', function (Blueprint $table) {
                if (Schema::hasColumn('contributions', 'month')) {
                    $table->unique(['member_id', 'academic_year_id', 'month'], 'contributions_member_year_month_unique');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
