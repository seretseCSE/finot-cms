<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tour_completed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role'); // The role for which the tour was completed
            $table->timestamp('completed_at')->default(now());
            $table->string('tour_version')->default('1.0'); // For future tour updates
            $table->json('tour_data')->nullable(); // Store tour progress data
            
            $table->unique(['user_id', 'role'], 'user_role_unique');
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tour_completed');
    }
};
