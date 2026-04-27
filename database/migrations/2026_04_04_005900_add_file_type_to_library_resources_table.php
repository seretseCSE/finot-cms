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
        Schema::table('library_resources', function (Blueprint $table) {
            $table->string('file_type', 20)->default('pdf')->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('library_resources', function (Blueprint $table) {
            $table->dropColumn('file_type');
        });
    }
};
