<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type', 100);
            $table->string('format', 20);
            $table->string('file_path', 500);
            $table->integer('record_count');
            $table->string('exported_by');
            $table->timestamp('created_at');
            
            // Indexes
            $table->index('resource_type');
            $table->index('created_at');
            $table->index('exported_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_logs');
    }
};
