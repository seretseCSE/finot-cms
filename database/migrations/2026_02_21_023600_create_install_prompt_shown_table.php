<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('install_prompt_shown', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('shown_at')->useCurrent();
            $table->timestamp('dismissed_until')->nullable();
            $table->unsignedInteger('visit_count')->default(0);

            $table->index(['user_id', 'shown_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('install_prompt_shown');
    }
};
