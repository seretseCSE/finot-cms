<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The stock ledger — the digital bin card. APPEND-ONLY: rows are never
 * updated or deleted; corrections are new adjustment movements. Written only
 * by App\Services\Inventory\StockLedger, which row-locks the stock_level and
 * stamps quantity_after (the running balance auditors read). quantity is
 * always positive; quantity_change carries the sign (+receive/return,
 * −issue/write_off, ± adjustment).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('type', 20); // receive|issue|return|adjustment|write_off
            $table->decimal('quantity', 12, 2);
            $table->decimal('quantity_change', 12, 2);
            $table->decimal('quantity_after', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable(); // snapshot at receive time
            $table->foreignId('requisition_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stock_take_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_name')->nullable(); // receives without a PO
            $table->string('recipient')->nullable(); // direct issues without a requisition
            $table->string('reference')->nullable(); // supplier voucher / delivery note no.
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'inventory_item_id', 'id']);
            $table->index(['branch_id', 'created_at']);
            $table->index(['branch_id', 'type']);
            $table->index('requisition_id');
            $table->index('purchase_order_id');
            $table->index('stock_take_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
