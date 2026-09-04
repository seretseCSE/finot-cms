<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Platform-wide operator knobs (Temari.et staff only) — the first
        // consumer is the SMS event whitelist (`notifications.sms_whitelist`):
        // SMS costs real money per message, so which catalog events may text
        // is a PLATFORM decision, never a school one. Key-value with a JSONB
        // payload; read through PlatformSetting::get() (cached).
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->jsonb('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
