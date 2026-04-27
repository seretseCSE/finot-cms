<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('duplicate_records', function (Blueprint $table) {
            $table->id();
            $table->string('model_type');
            $table->unsignedBigInteger('primary_record_id');
            $table->unsignedBigInteger('duplicate_record_id');
            $table->json('match_criteria')->nullable();
            $table->enum('status', ['pending', 'merged', 'ignored'])->default('pending');
            $table->timestamp('merged_at')->nullable();
            $table->unsignedBigInteger('merged_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'status']);
            $table->index(['primary_record_id', 'duplicate_record_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_records');
    }
};
