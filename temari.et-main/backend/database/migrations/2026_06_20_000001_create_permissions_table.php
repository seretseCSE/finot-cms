<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spatie permission catalog (split from the published package migration into
 * one table per file). These tables define WHAT each role can do; WHERE a user
 * holds a role lives exclusively in `memberships` (ADR-010) — the model_has_*
 * pivots stay empty by design.
 */
return new class () extends Migration {
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('permission.table_names')['permissions']);
    }
};
