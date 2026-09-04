<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tutor verification documents (degree, experience letters, Fayda scan…),
 * private on R2, reviewer-visible. Teachers may import from their employee
 * file — the import COPIES the object reference and stamps provenance
 * (source_employee_attachment_id); the school's original is never touched.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tutor_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 40)->nullable(); // DocumentCategory
            $table->string('path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('source_employee_attachment_id')->nullable()
                ->constrained('employee_attachments')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_attachments');
    }
};
