<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One spreadsheet row of a student import, held as the CANONICAL registration
 * payload (`data`: student fields + guardians[] + enrollment ids — all fuzzy
 * parsing happened client-side). Validated on arrival and revalidated on every
 * inline fix; `issues` carries field-keyed errors/warnings for the studio
 * grid. A duplicate match records the student and a per-row `resolution`
 * (skip | create | enroll_existing). After commit the row is the audit trail:
 * which student it produced, or why it failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->jsonb('data');
            $table->string('status', 20)->default('ready');
            // [{ field, message, level: "error"|"warning"|"info" }, …]
            $table->jsonb('issues')->nullable();
            $table->foreignId('duplicate_student_id')->nullable()->constrained('students');
            $table->string('resolution', 20)->nullable();
            $table->foreignId('student_id')->nullable()->constrained('students');
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->unique(['student_import_id', 'row_number']);
            $table->index(['student_import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_import_rows');
    }
};
