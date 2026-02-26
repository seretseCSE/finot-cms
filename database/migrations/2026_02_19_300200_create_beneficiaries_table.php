<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->string('beneficiary_code', 20)->unique(); // B-000001 format
            $table->string('full_name', 255);
            $table->string('phone', 20)->unique();
            $table->text('address');
            $table->enum('type', ['Individual', 'Family', 'Organization']);
            $table->string('need_category', 100);
            $table->string('email', 191)->nullable();
            $table->string('id_number', 100)->nullable();
            $table->integer('dependents_count')->nullable();
            $table->decimal('monthly_income', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['Active', 'Inactive', 'Completed'])->default('Active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('type');
            $table->index('need_category');
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
