<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which branches use each school account. Every attachment has its own
 * is_active so one branch can stop using a shared account without breaking
 * the others.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bank_account_branch', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true); // per-branch switch
            $table->timestamps();

            $table->unique(['bank_account_id', 'branch_id']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_account_branch');
    }
};
