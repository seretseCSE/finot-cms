<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily staff register: one recorded mark per employee per day
 * (present|late|half_day|absent|excused). On-leave and holiday are NOT
 * statuses — they are computed overlays from approved leave requests and the
 * holiday calendar (see EmployeeAttendanceController::roster).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('employee_attendance_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20); // present|late|half_day|absent|excused
            // 'manual' (register UI) or 'device' (RFID scan). Manual wins on
            // status; device fills blank times only.
            $table->string('source', 10)->default('manual');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_attendance_records');
    }
};
