<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->string('donor_name', 255)->nullable(); // null allows Anonymous
            $table->decimal('amount', 10, 2);
            $table->date('donation_date');
            $table->string('donation_type', 100);
            $table->string('custom_donation_type', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Check constraint for minimum amount
            // $table->check('amount >= 0.01', 'check_donation_amount_minimum');
            
            // Indexes for performance
            $table->index('donation_date');
            $table->index('donation_type');
            $table->index('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
