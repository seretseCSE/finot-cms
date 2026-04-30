<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_post_id')->constrained('blog_posts')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name');
            $table->string('email');
            $table->text('content');
            $table->boolean('is_approved')->default(false);
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->onDelete('cascade');
            $table->timestamps();

            $table->index('blog_post_id');
            $table->index('is_approved');
            $table->index('parent_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
    }
};
