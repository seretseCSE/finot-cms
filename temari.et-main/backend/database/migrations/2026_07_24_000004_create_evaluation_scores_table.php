<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-criterion score lines of one appraisal. Domain/label/weight/max are
 * SNAPSHOTTED from the template at evaluation creation (payroll-freeze
 * pattern) — later template edits never change what a teacher signed.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('evaluation_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_criterion_id')->nullable()->constrained('evaluation_criteria')->nullOnDelete();
            $table->string('domain', 64);
            $table->string('label');
            $table->decimal('weight', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->decimal('score', 5, 2)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('teacher_evaluation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_scores');
    }
};
