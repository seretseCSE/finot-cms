<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requisition lines. The approver may trim quantities (quantity_approved,
 * defaulting to the requested amount at approval); quantity_issued accrues
 * as the storekeeper fulfils — the ledger movement is the source of truth.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('requisition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_requested', 12, 2);
            $table->decimal('quantity_approved', 12, 2)->nullable();
            $table->decimal('quantity_issued', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['requisition_id', 'inventory_item_id']);
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_items');
    }
};
