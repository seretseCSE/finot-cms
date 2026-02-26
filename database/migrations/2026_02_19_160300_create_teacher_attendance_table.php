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
        Schema::create('teacher_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();

            $table->enum('attendance_status', ['Present', 'Absent', 'Late', 'Permission']);
            $table->foreignId('marked_by')->constrained('users');
            $table->timestamp('marked_at');

            $table->enum('session_outcome', ['Normal', 'Cancelled', 'Substitute_Assigned'])->default('Normal');
            $table->string('substitute_teacher_name', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['teacher_id', 'session_id'], 'ta_teacher_session_unique');
            $table->index(['session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attendance');
    }
};
