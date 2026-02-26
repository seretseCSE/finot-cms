<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 20)->unique()->nullable(); // INV-000001 format
            $table->string('name', 255);
            $table->enum('category', ['Electronics', 'Furniture', 'Books', 'Supplies', 'Equipment', 'Other']);
            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('unit', 50); // pieces/boxes/sets/kg/liters/Other
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->string('supplier', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->enum('status', ['Active', 'Damaged', 'Lost', 'Disposed'])->default('Active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('item_code');
            $table->index('category');
            $table->index('status');
            $table->index('location');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
