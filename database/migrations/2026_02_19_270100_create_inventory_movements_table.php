<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->onDelete('cascade');
            $table->enum('movement_type', ['Stock In', 'Stock Out']);
            $table->string('sub_type', 100); // Purchase/Donation/Return/Usage/Distribution/Loan/Loss
            $table->decimal('quantity', 10, 2);
            $table->date('movement_date');
            $table->string('recipient_source', 255)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('override_justification')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('item_id');
            $table->index('movement_type');
            $table->index('movement_date');
            $table->index('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
