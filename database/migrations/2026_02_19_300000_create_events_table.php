<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->dateTime('date_time');
            $table->string('location', 500);
            $table->text('description')->nullable();
            $table->string('featured_image', 500)->nullable();
            $table->boolean('registration_required')->default(false);
            $table->integer('max_capacity')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->enum('status', ['Draft', 'Published', 'Full', 'Ongoing', 'Completed', 'Cancelled'])->default('Draft');
            $table->enum('recurrence_type', ['None', 'Weekly', 'Monthly', 'Custom'])->default('None');
            $table->date('recurrence_end_date')->nullable();
            $table->foreignId('parent_event_id')->nullable()->constrained('events')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('date_time');
            $table->index('recurrence_type');
            $table->index('parent_event_id');
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
