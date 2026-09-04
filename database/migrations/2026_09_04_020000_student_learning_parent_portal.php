<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('member_id')->constrained('parents')->nullOnDelete();
            }
        });

        Schema::create('class_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamp('event_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['class_id', 'is_published', 'published_at']);
        });

        Schema::create('homework_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['class_id', 'is_published', 'due_at']);
        });

        Schema::create('class_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['class_id', 'is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_materials');
        Schema::dropIfExists('homework_assignments');
        Schema::dropIfExists('class_announcements');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
        });
    }
};
