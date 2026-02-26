<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('media_categories')->onDelete('cascade');
            $table->string('name', 100);
            $table->integer('display_order')->default(0);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('category_id');
            $table->index('status');
            $table->index('display_order');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_subcategories');
    }
};
