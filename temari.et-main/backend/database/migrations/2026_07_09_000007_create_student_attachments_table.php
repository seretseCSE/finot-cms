<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student document (birth certificate, ID, transfer letter…) stored
 * privately on R2. Only the storage path is persisted; access always goes
 * through signed URLs — same pattern as employee_attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Ethiopian document type (App\Enums\DocumentCategory) — birth
            // certificate, ketebat/vaccination card, kebele ID, Fayda…
            $table->string('category', 50)->nullable();
            $table->string('path', 2048);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            // Provenance: which scope collected the document and who uploaded
            // it. Documents travel FORWARD with the student on transfer; the
            // stamp keeps the audit trail and powers "Uploaded by …" in the UI.
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Retention (ADR-017): a document referenced by a handover snapshot
            // is part of a former school's frozen file — "deleting" it only
            // soft-deletes (hides it from the live record); the row and the R2
            // object survive so era archives keep opening it.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attachments');
    }
};
