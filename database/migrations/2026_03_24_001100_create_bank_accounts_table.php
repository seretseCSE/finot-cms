<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 50)->unique();
            $table->string('account_name');
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('account_type')->default('current'); // current, savings, fixed_deposit
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('ETB');

            // Contact details
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['bank_name', 'is_active']);
            $table->index('account_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
