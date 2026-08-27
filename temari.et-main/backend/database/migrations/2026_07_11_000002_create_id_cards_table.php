<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MIFARE ID cards, school-owned, attached to a Student or Employee (morph).
 * A chip UID is globally unique in hardware, so at most one ACTIVE row may
 * hold it (partial unique index) — and a holder carries at most one active
 * card. Lost/revoked cards stay as history; `replaced_by_id` chains a
 * replacement. branch_id is denormalized from the holder at issue time so
 * card lists scope like every other branch register.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('card_uid', 32);
            $table->morphs('holder'); // App\Models\Student | App\Models\Employee
            $table->string('status', 10)->default('active'); // active|lost|revoked|replaced
            $table->foreignId('replaced_by_id')->nullable()->constrained('id_cards')->nullOnDelete();
            $table->date('issued_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->index('card_uid');
        });

        DB::statement(
            "CREATE UNIQUE INDEX id_cards_active_uid ON id_cards (card_uid) WHERE status = 'active' AND deleted_at IS NULL"
        );
        DB::statement(
            "CREATE UNIQUE INDEX id_cards_active_holder ON id_cards (holder_type, holder_id) WHERE status = 'active' AND deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('id_cards');
    }
};
