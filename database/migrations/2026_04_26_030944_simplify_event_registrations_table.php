<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            // Drop foreign key and indexes first
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['guest_email']);
            $table->dropIndex(['registration_type']);

            // Remove old complex fields
            $table->dropColumn(['user_id', 'guest_name', 'guest_email', 'guest_phone', 'registration_type']);

            // Add simple registration fields
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Add indexes
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            // Remove simple fields
            $table->dropColumn(['name', 'email', 'phone']);
            $table->dropIndex(['email']);

            // Restore old complex fields
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->enum('registration_type', ['user', 'guest'])->default('user');
            $table->index('registration_type');
            $table->index('guest_email');
        });
    }
};
