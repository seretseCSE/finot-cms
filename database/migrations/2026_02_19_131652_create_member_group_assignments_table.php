<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('member_group_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->foreignId('group_id')->constrained('member_groups')->onDelete('cascade');
            $table->date('effective_from')->comment('Date assignment becomes effective');
            $table->date('effective_to')->nullable()->comment('Date assignment ends (null = still active)');
            $table->foreignId('assigned_by')->constrained('users')->comment('User who assigned member');
            $table->foreignId('removed_by')->nullable()->constrained('users')->comment('User who removed member');
            $table->timestamps();
            
            // Index for fast active group lookup
            $table->index(['member_id', 'effective_to']);
            $table->index(['group_id', 'effective_to']);
            $table->index(['effective_from']);
            $table->index(['effective_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_group_assignments');
    }
};
