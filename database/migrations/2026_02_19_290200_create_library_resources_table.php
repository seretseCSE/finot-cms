<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('file_path', 500);
            $table->foreignId('category_id')->constrained('library_categories')->onDelete('cascade');
            $table->foreignId('subcategory_id')->nullable()->constrained('library_subcategories')->onDelete('set null');
            $table->text('description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('file_size_kb');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('category_id');
            $table->index('subcategory_id');
            $table->index('is_featured');
            $table->index('is_active');
            $table->index('uploaded_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_resources');
    }
};
