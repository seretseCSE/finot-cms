<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expense']);
            $table->string('transaction_id', 20)->unique(); // FT-000001 format
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('ETB');

            // Transaction details
            $table->string('category')->nullable(); // Salary, Utilities, Rent, etc.
            $table->string('source')->nullable(); // Church, Donation, etc.
            $table->date('transaction_date');
            $table->string('payment_method')->nullable(); // Cash, Bank Transfer, etc.

            // Bank account
            $table->unsignedBigInteger('bank_account_id')->nullable();

            // File attachments
            $table->string('attachment_path')->nullable();
            $table->string('attachment_type')->nullable(); // receipt, invoice, etc.

            // Audit fields
            $table->foreignId('recorded_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'transaction_date']);
            $table->index('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
