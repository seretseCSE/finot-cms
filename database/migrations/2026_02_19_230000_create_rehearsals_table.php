<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehearsals', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_time');
            $table->string('location', 255);
            $table->enum('status', ['Scheduled', 'Completed', 'Cancelled'])->default('Scheduled');
            $table->enum('recurrence_type', ['None', 'Weekly', 'Biweekly', 'Monthly'])->default('None');
            $table->date('recurrence_end_date')->nullable();
            $table->json('songs')->nullable(); // array of song_ids
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Indexes
            $table->index('date_time');
            $table->index('status');
            $table->index('recurrence_type');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehearsals');
    }
};
