<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The leave workflow: staff request for themselves (`leave.request_own`),
 * managers request on behalf and decide (`leave.manage`). Approved leave is
 * OVERLAYED onto the staff attendance roster at read time — never materialised
 * as attendance rows, so the two sources can't drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            // Working days consumed (computed server-side: excludes weekends +
            // holidays). Half-day requests store 0.5.
            $table->decimal('days', 5, 1);
            $table->boolean('is_half_day')->default(false);
            $table->text('reason')->nullable();

            $table->string('status', 20)->default('pending'); // pending|approved|rejected|cancelled
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['employee_id', 'start_date']);
            // Overlap checks scan approved requests around a date window.
            $table->index(['employee_id', 'status', 'start_date', 'end_date'], 'leave_requests_overlap_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
