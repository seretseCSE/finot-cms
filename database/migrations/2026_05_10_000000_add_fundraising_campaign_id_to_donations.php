<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->foreignId('fundraising_campaign_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('fundraising_campaigns')
                ->onDelete('set null');

            $table->index('fundraising_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropForeign(['fundraising_campaign_id']);
            $table->dropColumn('fundraising_campaign_id');
        });
    }
};
