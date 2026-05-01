<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('title_am', 255)->nullable();
            $table->longText('content');
            $table->longText('content_am')->nullable();
            $table->string('image')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('is_global')->default(false);
            $table->string('target_audience', 50)->default('all_users');
            $table->json('broadcast_channels')->nullable();
            $table->json('acknowledged_by')->nullable();
            $table->timestamp('broadcast_at')->nullable();
            $table->enum('status', ['Draft', 'Scheduled', 'Active', 'Expired', 'Archived'])->default('Draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('start_date');
            $table->index('end_date');
            $table->index('is_urgent');
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
