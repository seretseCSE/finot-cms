<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('library_resources', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('description');
            $table->longText('content_am')->nullable()->after('content');
            $table->string('icon')->nullable()->after('file_type');
            $table->string('featured_image')->nullable()->after('icon');
        });

        Schema::table('library_resources', function (Blueprint $table) {
            $table->string('file_path', 500)->nullable()->change();
            $table->integer('file_size_kb')->nullable()->change();
            $table->foreignId('uploaded_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('library_resources', function (Blueprint $table) {
            $table->dropColumn(['content', 'content_am', 'icon', 'featured_image']);
        });
    }
};
