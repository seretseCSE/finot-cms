<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('member_groups')->onDelete('cascade');
            $table->string('month_name', 50); // Ethiopian or Gregorian month name
            $table->decimal('amount', 10, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Constraints
            $table->unique(['group_id', 'month_name', 'effective_from'], 'unique_group_month_effective');
            // $table->check('amount >= 0.01', 'check_amount_minimum'); // Blueprint::check does not exist
            
            // Indexes for performance
            $table->index(['group_id', 'month_name']);
            $table->index('effective_from');
            $table->index('effective_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_amounts');
    }
};
