<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('song_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('display_order');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('song_categories');
    }
};
