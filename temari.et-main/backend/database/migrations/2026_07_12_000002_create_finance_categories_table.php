<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SCHOOL-owned chart of cashbook categories, one row per (school, kind,
 * name): `expense` rows classify money out (utilities, rent, supplies…),
 * `income` rows classify non-fee money in (hall rental, uniform sales…).
 * Auto-provisioned per school from App\Support\FinanceCategories defaults;
 * referenced categories are deactivated, never deleted (platform-catalog
 * convention). Deliberately NOT a double-entry chart of accounts — Temari is
 * the school's finance office, the accountant's GL lives in their own tools.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('finance_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10); // expense|income
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index finance_categories_school_id_kind_name_unique'
            .' on finance_categories (school_id, kind, name) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_categories');
    }
};
