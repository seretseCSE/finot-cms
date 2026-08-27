<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff documents (credentials, IDs, contracts…) stored privately on R2 —
 * path only, access always through signed URLs. A file may additionally
 * anchor to one position (contract) or one qualification (degree scan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_qualification_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('path', 2048);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attachments');
    }
};
