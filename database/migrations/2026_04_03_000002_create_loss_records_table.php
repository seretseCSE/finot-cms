<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('loss_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->enum('loss_type', ['Lost', 'Damaged', 'Disposed']);
            $table->integer('quantity');
            $table->text('reason')->nullable();
            $table->date('loss_date');
            $table->string('reference_number')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loss_records');
    }
};
