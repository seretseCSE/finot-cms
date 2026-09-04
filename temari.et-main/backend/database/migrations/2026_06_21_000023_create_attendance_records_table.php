<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily attendance: one row per student per section per date. School-X mixed
 * attendance modes; v1 ships daily (homeroom) mode — per-period can layer on
 * later via a nullable period/teaching_assignment FK. Denormalized school/branch
 * + academic_year for fast scoped reporting.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            // term_id is the universal time anchor (CLAUDE.md §3); derived from
            // the date by SaveAttendanceAction and gated by TermGate.
            $table->foreignId('term_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('status');
            // Who produced the mark: 'manual' (register UI) or 'device' (RFID
            // scan / auto-absent). Manual wins on status; device fills blanks.
            $table->string('source', 10)->default('manual');
            // The terminal that produced a device mark — drives the per-device
            // attendance reports. FK constraint lives in the devices migration
            // (devices is created after this table).
            $table->unsignedBigInteger('device_id')->nullable()->index();
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['student_id', 'section_id', 'date']);
            $table->index(['section_id', 'date']);
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
