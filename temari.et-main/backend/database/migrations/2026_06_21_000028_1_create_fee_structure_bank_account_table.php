<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Collection accounts a fee accepts payments into. Empty = no preferred
 * account (cashier picks at payment time). One or many accounts are allowed —
 * schools often accept Telebirr + CBE for the same fee.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('fee_structure_bank_account', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();

            $table->unique(['fee_structure_id', 'bank_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_structure_bank_account');
    }
};
