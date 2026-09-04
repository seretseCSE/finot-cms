<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The physical card fulfilment pipeline. Card ISSUANCE is Temari.et platform
 * territory (we print and deliver the MIFARE cards); schools report a lost/
 * damaged card (or ask for a new one) and this row tracks the request from
 * `requested` through preparing/delivering to `delivered`. Issuing the
 * replacement links `new_card_id`, so the school sees the whole chain.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('card_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            // The card being replaced — null for reason=new (no prior card).
            $table->foreignId('id_card_id')->nullable()->constrained('id_cards')->nullOnDelete();
            // Denormalized from the card/holder so the pipeline lists cheaply.
            $table->morphs('holder');
            $table->string('reason', 10)->default('lost'); // lost|damaged|new
            $table->string('note', 500)->nullable();
            // requested|accepted|preparing|delivering|delivered|rejected
            $table->string('status', 10)->default('requested');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('new_card_id')->nullable()->constrained('id_cards')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'status']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_requests');
    }
};
