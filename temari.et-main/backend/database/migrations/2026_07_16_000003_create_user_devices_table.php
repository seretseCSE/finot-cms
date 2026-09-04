<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Devices a user has signed in from — powers the `security.new_device`
        // notification. The fingerprint is a hash of the client user-agent
        // (never the raw string, never the IP — coarse and privacy-light on
        // purpose): a first-seen fingerprint means "new device", and the user
        // is told immediately, in-app + SMS. Rows are delivery state, not
        // domain history — no soft deletes.
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint', 64);
            // Human-readable summary parsed from the user-agent ("Chrome on
            // Android") shown in the security notification.
            $table->string('label', 80)->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
