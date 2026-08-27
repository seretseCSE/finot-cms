<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment collection accounts. A BANK ACCOUNT is owned by the SCHOOL (so
 * branches can share it) and attached to branches through
 * `bank_account_branch`. Fees attach 0..n accounts via
 * `fee_structure_bank_account`; payments snapshot the chosen account —
 * re-pointing a fee never rewrites payment history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->string('account_name');
            // Bank account number, or wallet phone number for wallet providers.
            $table->string('account_number', 50);
            $table->boolean('is_active')->default(true); // school-level switch
            $table->timestamps();
            $table->softDeletes();

            $table->index('school_id');
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index bank_accounts_school_id_bank_id_account_number_unique'
            .' on bank_accounts (school_id, bank_id, account_number) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
