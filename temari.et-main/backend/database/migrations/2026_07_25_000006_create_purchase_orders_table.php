<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPTIONAL procurement lane: a purchase order is never required — direct
 * receiving into the ledger always works without one. When a school does
 * raise POs, the flow is pending → approved/declined (four-eyes, never the
 * one who raised it) → received once every line lands. `total_cost` is a
 * cached sum of the lines for list performance.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('supplier_name');
            $table->string('supplier_phone', 20)->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|declined|received|cancelled
            $table->date('expected_on')->nullable();
            $table->string('note')->nullable();
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
