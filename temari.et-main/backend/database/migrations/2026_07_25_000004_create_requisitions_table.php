<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store requisitions (the Model-22 workflow): any staff member requests
 * items, someone with inventory.approve decides — never their own request —
 * and the storekeeper issues against the approved lines (partial issues
 * allowed; status flips to `issued` when every approved line is fulfilled).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending'); // pending|approved|declined|issued|cancelled
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('purpose')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index('requested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');
    }
};
