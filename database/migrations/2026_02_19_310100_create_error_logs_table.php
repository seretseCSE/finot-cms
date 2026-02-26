<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('error_type', 255);
            $table->text('error_message');
            $table->longText('stack_trace');
            $table->integer('user_id')->nullable();
            $table->string('url', 500);
            $table->string('http_method', 10);
            $table->json('request_data')->nullable();
            $table->text('user_agent');
            $table->timestamp('created_at');
            
            // Indexes
            $table->index('error_type');
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};
