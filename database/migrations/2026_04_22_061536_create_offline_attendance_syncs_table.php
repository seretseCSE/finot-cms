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
        Schema::create('offline_attendance_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->enum('status', ['Present', 'Absent', 'Excused', 'Late', 'Permission']);
            $table->timestamp('marked_at');
            $table->enum('sync_status', ['pending', 'synced', 'conflict'])->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->text('conflict_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sync_status']);
            $table->index(['session_id', 'sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_attendance_syncs');
    }
};
