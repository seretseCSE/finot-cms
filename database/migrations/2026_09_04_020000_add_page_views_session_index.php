<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('page_views')) {
            return;
        }

        Schema::table('page_views', function (Blueprint $table) {
            $table->index(['created_at', 'session_hash'], 'page_views_created_at_session_hash_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('page_views')) {
            return;
        }

        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex('page_views_created_at_session_hash_index');
        });
    }
};
