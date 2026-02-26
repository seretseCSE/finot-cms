<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->enum('type', ['Photo', 'Video'])->notNull();
            $table->foreignId('category_id')->constrained('media_categories')->onDelete('restrict');
            $table->foreignId('subcategory_id')->nullable()->constrained('media_subcategories')->onDelete('set null');
            $table->text('description')->nullable();
            $table->string('file_path', 500);
            $table->integer('file_size_kb');
            $table->string('event_album', 255)->nullable();
            $table->text('tags')->nullable();
            $table->enum('visibility', ['Public', 'Members Only', 'Department Only'])->default('Public');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('visibility');
            $table->index('department_id');
            $table->index('uploaded_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
