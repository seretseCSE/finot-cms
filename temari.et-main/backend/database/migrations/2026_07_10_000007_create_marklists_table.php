<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The marklist workflow row: one per subject assignment (which is already
 * term-scoped), tracking the Ethiopian sign-off ritual — the teacher fills
 * and SUBMITS the marklist, a supervisor (director/registrar lane:
 * grades.approve) APPROVES or reopens it. Any non-draft marklist is
 * read-only for marks; report cards should only ever be printed from
 * approved marklists. Created lazily the first time a continuous assessment is opened.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('marklists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subject_assignment_id')->unique()->constrained()->cascadeOnDelete();
            // Denormalized scope + anchor for fast approval-queue lists.
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            // draft | submitted | approved
            $table->string('status', 12)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'term_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marklists');
    }
};
