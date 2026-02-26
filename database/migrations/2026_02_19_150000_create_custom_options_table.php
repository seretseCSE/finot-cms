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
        Schema::create('custom_options', function (Blueprint $table) {
            $table->id();
            $table->string('field_name', 100);
            $table->string('option_value', 255);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('added_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();

            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('display_order')->nullable();

            $table->softDeletes();

            $table->index(['field_name', 'status']);
            $table->index(['field_name', 'option_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_options');
    }
};
