<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aid_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->onDelete('cascade');
            $table->date('distribution_date');
            $table->string('aid_type', 100);
            $table->decimal('amount', 10, 2);
            $table->foreignId('distributed_by')->constrained('users')->onDelete('cascade');
            $table->string('receipt_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('beneficiary_id');
            $table->index('distribution_date');
            $table->index('aid_type');
            $table->index('distributed_by');
            $table->index('is_locked');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aid_distributions');
    }
};
