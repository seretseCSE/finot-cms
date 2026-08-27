<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line per item in a counting session. expected_quantity is frozen when
 * the line is added; counted_quantity NULL = not counted yet (posting skips
 * it rather than guessing zero).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_take_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('expected_quantity', 12, 2);
            $table->decimal('counted_quantity', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['stock_take_id', 'inventory_item_id']);
            $table->index('inventory_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_take_lines');
    }
};
