<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('title_am', 255)->nullable();
            $table->longText('content');
            $table->longText('content_am')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->date('publish_date')->nullable();
            $table->string('featured_image', 500)->nullable();
            $table->text('tags')->nullable();
            $table->enum('status', ['Draft', 'Scheduled', 'Published', 'Archived'])->default('Draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('publish_date');
            $table->index('published_at');
            $table->index('author_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
