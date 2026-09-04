<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Self-signup phone verification (mirrors sms_password_resets): the
        // OTP proves possession of the number BEFORE any account is created
        // or activated — an unverified phone must never squat on a parent's
        // provisioned identity.
        Schema::create('signup_otps', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('token', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_otps');
    }
};
