<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS PIN-reset OTPs. Keyed by USER, not by phone — a phone-less ID-login
 * student has no phone of their own; their OTP is delivered to the primary
 * guardian's phone but still belongs to the student's account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_password_resets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_password_resets');
    }
};
