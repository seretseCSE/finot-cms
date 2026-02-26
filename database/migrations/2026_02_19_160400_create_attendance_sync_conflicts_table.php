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
        Schema::create('attendance_sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();

            $table->foreignId('first_user_id')->constrained('users');
            $table->enum('first_value', ['Present', 'Absent', 'Excused', 'Late', 'Permission']);
            $table->timestamp('first_synced_at')->nullable();
            $table->foreignId('second_user_id')->constrained('users');
            $table->enum('second_value', ['Present', 'Absent', 'Excused', 'Late', 'Permission']);
            $table->timestamp('second_synced_at')->nullable();

            $table->enum('winner_value', ['Present', 'Absent', 'Excused', 'Late', 'Permission']);

            $table->timestamps();

            $table->index(['session_id']);
            $table->index(['student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sync_conflicts');
    }
};
