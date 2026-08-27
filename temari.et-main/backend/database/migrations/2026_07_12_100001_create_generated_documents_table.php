<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registry + cache of every backend-generated official PDF (receipts,
 * transfer/withdrawal letters, transcripts, report cards, statements,
 * payslips). One row per (type, subject, params, content-version): documents
 * are pre-rendered by queue jobs, stored on R2 and re-served from here —
 * never re-rendered on click. `public_token` backs the QR verification page
 * for document types without their own public token lane.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->nullableMorphs('subject');
            // Non-model anchors: statement windows, term ids, …
            $table->jsonb('params')->nullable();
            // sha256 over the rendered payload — same hash = same PDF, reuse it.
            $table->string('version_hash', 64);
            $table->string('status', 20)->default('queued');
            $table->string('disk_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('error', 500)->nullable();
            $table->uuid('public_token')->unique();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['type', 'subject_type', 'subject_id', 'version_hash'],
                'generated_documents_cache_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
    }
};
