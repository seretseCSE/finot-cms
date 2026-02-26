<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehearsal_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rehearsal_id')->constrained('rehearsals')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->enum('status', ['Present', 'Absent', 'Excused', 'Late', 'Permission'])->default('Absent');
            $table->foreignId('marked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('marked_at')->nullable();
            $table->timestamps();

            // Unique constraint: one attendance record per member per rehearsal
            $table->unique(['rehearsal_id', 'member_id']);

            // Indexes
            $table->index('rehearsal_id');
            $table->index('member_id');
            $table->index('status');
            $table->index('marked_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehearsal_attendance');
    }
};
