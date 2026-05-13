<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // attendance_sessions
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->index('locked_by');
            $table->index('unlocked_by');
            $table->index('created_by');
            $table->index('created_at');
        });

        // contributions
        Schema::table('contributions', function (Blueprint $table) {
            $table->index('recorded_by');
            $table->index('status');
            $table->index('created_at');
        });

        // donations
        Schema::table('donations', function (Blueprint $table) {
            $table->index('bank_account_id');
            $table->index('created_at');
        });

        // tours
        Schema::table('tours', function (Blueprint $table) {
            $table->index('cancelled_by');
            $table->index('created_at');
        });

        // student_enrollments
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->index('enrolled_by');
            $table->index('completed_by');
            $table->index('promoted_to_enrollment_id');
            $table->index('created_at');
        });

        // student_attendance
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->index('marked_by');
            $table->index('status');
            $table->index('created_at');
        });

        // teacher_attendance
        Schema::table('teacher_attendance', function (Blueprint $table) {
            $table->index('marked_by');
            $table->index('attendance_status');
            $table->index('session_outcome');
            $table->index('created_at');
        });

        // teacher_assignments
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('created_at');
        });

        // member_group_assignments
        Schema::table('member_group_assignments', function (Blueprint $table) {
            $table->index('assigned_by');
            $table->index('removed_by');
            $table->index('created_by');
            $table->index('created_at');
        });

        // financial_transactions
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->index('recorded_by');
            $table->index('approved_by');
            $table->index('created_at');
        });

        // fundraising_campaigns
        Schema::table('fundraising_campaigns', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('status');
            $table->index('created_at');
        });

        // songs
        Schema::table('songs', function (Blueprint $table) {
            $table->index('created_at');
        });

        // inventory_items
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->index('created_at');
        });

        // rehearsals
        Schema::table('rehearsals', function (Blueprint $table) {
            $table->index('created_at');
        });

        // tour_passengers
        Schema::table('tour_passengers', function (Blueprint $table) {
            $table->index('registered_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // attendance_sessions
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex(['locked_by']);
            $table->dropIndex(['unlocked_by']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['created_at']);
        });

        // contributions
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropIndex(['recorded_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        // donations
        Schema::table('donations', function (Blueprint $table) {
            $table->dropIndex(['bank_account_id']);
            $table->dropIndex(['created_at']);
        });

        // tours
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['cancelled_by']);
            $table->dropIndex(['created_at']);
        });

        // student_enrollments
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropIndex(['enrolled_by']);
            $table->dropIndex(['completed_by']);
            $table->dropIndex(['promoted_to_enrollment_id']);
            $table->dropIndex(['created_at']);
        });

        // student_attendances
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropIndex(['marked_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        // teacher_attendance
        Schema::table('teacher_attendance', function (Blueprint $table) {
            $table->dropIndex(['marked_by']);
            $table->dropIndex(['attendance_status']);
            $table->dropIndex(['session_outcome']);
            $table->dropIndex(['created_at']);
        });

        // teacher_assignments
        Schema::table('teacher_assignments', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['created_at']);
        });

        // member_group_assignments
        Schema::table('member_group_assignments', function (Blueprint $table) {
            $table->dropIndex(['assigned_by']);
            $table->dropIndex(['removed_by']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['created_at']);
        });

        // financial_transactions
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex(['recorded_by']);
            $table->dropIndex(['approved_by']);
            $table->dropIndex(['created_at']);
        });

        // fundraising_campaigns
        Schema::table('fundraising_campaigns', function (Blueprint $table) {
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        // songs
        Schema::table('songs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        // inventory_items
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        // rehearsals
        Schema::table('rehearsals', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        // tour_passengers
        Schema::table('tour_passengers', function (Blueprint $table) {
            $table->dropIndex(['registered_by']);
            $table->dropIndex(['created_at']);
        });
    }
};
