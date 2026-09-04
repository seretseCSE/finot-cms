<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stock take is one counting session (optionally scoped to a category):
 * lines snapshot the expected quantity when counting starts, the storekeeper
 * records what was actually found, and POSTING writes the differences to the
 * ledger as adjustment movements — the count itself never edits stock.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('stock_takes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('in_progress'); // in_progress|posted|cancelled
            $table->string('note')->nullable();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_takes');
    }
};
