<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('tour_attendance_sessions')->onDelete('cascade');
            $table->foreignId('passenger_id')->constrained('tour_passengers')->onDelete('cascade');
            $table->enum('status', ['Present', 'Not Present'])->default('Not Present');
            $table->timestamp('marked_at')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('session_id');
            $table->index('passenger_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_attendance');
    }
};
