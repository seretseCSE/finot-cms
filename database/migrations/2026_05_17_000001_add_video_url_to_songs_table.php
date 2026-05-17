<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('video_url', 500)->nullable()->after('audio_file');
            $table->dropColumn('video_file');
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('video_file', 500)->nullable()->after('audio_file');
            $table->dropColumn('video_url');
        });
    }
};
