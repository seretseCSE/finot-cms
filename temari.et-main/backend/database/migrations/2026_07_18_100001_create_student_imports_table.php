<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One bulk student-import session: the registrar's file is parsed in the
 * BROWSER (the .xlsx never uploads); mapped rows arrive as JSON chunks into
 * student_import_rows, get server-validated in place, and a queued job later
 * executes the clean ones through RegisterStudentAction — one transaction per
 * row, partial-safe. `column_map` remembers the school's header mapping for
 * the next file; `options` carries the commit toggles (send_sms defaults OFF —
 * a wrong file must never become an SMS storm).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('student_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            // Import-wide defaults a row may override with its own columns.
            $table->foreignId('grade_level_id')->nullable()->constrained();
            $table->foreignId('section_id')->nullable()->constrained();
            $table->foreignId('school_program_id')->nullable()->constrained();
            $table->string('file_name', 255);
            $table->string('status', 20)->default('draft');
            // header → field mapping as chosen in the studio (reused next time).
            $table->jsonb('column_map')->nullable();
            // { send_sms: bool, create_student_accounts: bool }
            $table->jsonb('options')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['school_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_imports');
    }
};
