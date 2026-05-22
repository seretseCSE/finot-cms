<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // 1. Drop foreign key and column from courses first (unblocks subcategories table)
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['subcategory_id']);
        });

        // Clear course_subcategories (being dropped — user confirmed data can be removed)
        DB::table('course_subcategories')->truncate();

        // Drop subcategory_id column from courses
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('subcategory_id');
        });

        // 2. Add parent_id, depth, slug to course_categories
        Schema::table('course_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('course_categories')
                ->nullOnDelete();

            $table->integer('depth')
                ->default(0)
                ->after('parent_id');

            $table->string('slug')
                ->nullable()
                ->unique()
                ->after('icon');
        });

        // 3. Drop old course_subcategories table
        Schema::dropIfExists('course_subcategories');
    }

    public function down(): void
    {
        // 1. Restore course_subcategories table
        Schema::create('course_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('course_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('name_am')->nullable();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->string('status')->default('Active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 2. Restore subcategory_id on courses
        Schema::table('courses', function (Blueprint $table) {
            $table->foreignId('subcategory_id')
                ->nullable()
                ->after('category_id')
                ->constrained('course_subcategories')
                ->nullOnDelete();
        });

        // 3. Drop added columns from course_categories
        Schema::table('course_categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'depth', 'slug']);
        });
    }
};
