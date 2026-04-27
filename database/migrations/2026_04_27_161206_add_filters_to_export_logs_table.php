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
        Schema::table('export_logs', function (Blueprint $table) {
            $table->json('filters')->nullable()->after('resource_type');
            $table->string('ip_address', 45)->nullable()->after('exported_by');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('export_logs', function (Blueprint $table) {
            $table->dropColumn(['filters', 'ip_address', 'user_agent']);
        });
    }
};
