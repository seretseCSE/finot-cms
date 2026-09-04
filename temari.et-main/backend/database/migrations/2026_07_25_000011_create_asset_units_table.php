<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The property register: one row per physical unit of an is_asset item, with
 * a public tag (printed/written on the thing itself), condition and status
 * lifecycle. Identity book only — QUANTITY truth stays in the stock ledger;
 * the two are deliberately separate books, like a bin card vs a property
 * register in a real Ethiopian store.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('asset_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('tag', 12); // PublicId code, written on the unit
            $table->string('serial_number', 120)->nullable();
            $table->string('condition', 20)->default('good'); // new|good|fair|poor|damaged
            $table->string('status', 20)->default('in_store'); // in_store|assigned|under_repair|lost|disposed
            $table->date('acquired_on')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'inventory_item_id']);
            $table->index(['branch_id', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX asset_units_tag_unique ON asset_units (tag) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_units');
    }
};
