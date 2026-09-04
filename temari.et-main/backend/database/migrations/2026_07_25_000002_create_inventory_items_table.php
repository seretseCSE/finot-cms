<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The item master (SCHOOL-owned so branches share one naming): what things
 * ARE — never how many there are. Quantities live in stock_levels (cached)
 * and stock_movements (the ledger). `is_asset` marks items whose units will
 * be tag-tracked in the asset register (phase 2); they still stock-count here.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 60)->nullable(); // school's own item code, optional
            $table->string('unit', 20)->default('piece');
            $table->boolean('is_asset')->default(false);
            $table->decimal('reorder_level', 12, 2)->nullable(); // at/below → low-stock alert
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'inventory_category_id']);
        });

        DB::statement('CREATE UNIQUE INDEX inventory_items_school_name_unique ON inventory_items (school_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX inventory_items_school_code_unique ON inventory_items (school_id, code) WHERE code IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX inventory_items_name_trgm ON inventory_items USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
