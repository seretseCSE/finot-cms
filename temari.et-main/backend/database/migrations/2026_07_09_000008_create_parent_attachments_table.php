<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A guardian document (ID, custody letter…) stored privately on R2. Only the
 * storage path is persisted; access always goes through signed URLs — same
 * pattern as employee_attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->string('name');
            // Ethiopian document type (App\Enums\DocumentCategory) — birth
            // certificate, ketebat/vaccination card, kebele ID, Fayda…
            $table->string('category', 50)->nullable();
            $table->string('path', 2048);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            // Provenance: which scope collected the document and who uploaded
            // it — mirrors student_attachments.
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_attachments');
    }
};
