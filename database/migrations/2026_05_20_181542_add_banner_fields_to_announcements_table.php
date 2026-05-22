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
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('show_on_banner')->default(false)->after('is_urgent');
            $table->string('urgency')->default('info')->after('show_on_banner');
            $table->string('label')->nullable()->after('urgency');
            $table->string('subtitle')->nullable()->after('label');
            $table->timestamp('expires_at')->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['show_on_banner', 'urgency', 'label', 'subtitle', 'expires_at']);
        });
    }
};
