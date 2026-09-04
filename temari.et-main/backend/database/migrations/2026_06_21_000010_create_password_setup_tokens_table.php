<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-use tokens for the SMS-driven "set your password" flow. The token is
 * stored hashed; the plaintext is only ever sent in the SMS link.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('password_setup_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_setup_tokens');
    }
};
