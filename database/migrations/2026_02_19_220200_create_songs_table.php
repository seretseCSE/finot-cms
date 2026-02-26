<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('song_code', 20)->unique(); // SONG-000001 format
            $table->string('title', 255);
            $table->longText('lyrics')->nullable();
            $table->foreignId('category_id')->constrained('song_categories')->onDelete('restrict');
            $table->foreignId('subcategory_id')->constrained('song_subcategories')->onDelete('restrict');
            $table->string('audio_file', 500)->nullable();
            $table->string('video_file', 500)->nullable();
            $table->string('artist', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('song_code');
            $table->index('title');
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('is_active');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};
