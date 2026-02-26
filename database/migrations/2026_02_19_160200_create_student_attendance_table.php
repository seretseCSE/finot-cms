<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();

            $table->enum('status', ['Present', 'Absent', 'Excused', 'Late', 'Permission']);
            $table->foreignId('marked_by')->constrained('users');
            $table->timestamp('marked_at');

            $table->timestamp('sync_timestamp')->nullable();
            $table->boolean('is_synced')->default(true);

            $table->timestamps();

            $table->unique(['student_id', 'session_id'], 'sa_student_session_unique');
            $table->index(['session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_attendance');
    }
};
