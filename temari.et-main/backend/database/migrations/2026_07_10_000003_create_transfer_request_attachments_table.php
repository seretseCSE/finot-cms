<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supporting documents on a transfer request (report card, fee-clearance
 * slip, parent's letter…), uploaded by the RECEIVING side when it requests
 * the student. Files live privately on R2; responses only ever expose signed
 * URLs — same pattern as student_attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_request_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_transfer_request_id')
                ->constrained('student_transfer_requests')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('path', 2048);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_request_attachments');
    }
};
