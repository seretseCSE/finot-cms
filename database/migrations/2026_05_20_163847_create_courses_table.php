<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_am')->nullable();
            $table->text('description')->nullable();
            $table->text('description_am')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('course_categories')->nullOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('course_subcategories')->nullOnDelete();
            $table->string('instructor')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('duration')->nullable();
            $table->string('featured_image')->nullable();
            $table->string('icon')->nullable();
            $table->string('status')->default('Draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
