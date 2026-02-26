<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('month_name', 50);
            $table->date('payment_date');
            $table->enum('payment_method', ['Cash', 'Check', 'Mobile Money', 'Bank Transfer', 'Other'])->default('Cash');
            $table->string('custom_payment_method', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['member_id', 'academic_year_id']);
            $table->index(['academic_year_id', 'is_archived']);
            $table->index('payment_date');
            $table->index('month_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
    }
};
