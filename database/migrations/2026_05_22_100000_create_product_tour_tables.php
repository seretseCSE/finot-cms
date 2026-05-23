<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tour_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('panel')->default('admin');
            $table->string('tour_key');
            $table->string('tour_version');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->unsignedInteger('progress_step')->default(0);
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('role');
            $table->index('panel');
            $table->index('tour_key');
            $table->unique(['user_id', 'role', 'panel', 'tour_key'], 'product_tour_completions_unique');
        });

        Schema::create('product_tour_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role')->nullable();
            $table->string('panel')->default('admin');
            $table->string('event_type');
            $table->string('tour_key');
            $table->string('step_key')->nullable();
            $table->string('page')->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->index('event_type');
            $table->index('tour_key');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tour_analytics');
        Schema::dropIfExists('product_tour_completions');
    }
};
