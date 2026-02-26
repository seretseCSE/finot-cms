<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('place', 255);
            $table->text('description');
            $table->date('tour_date');
            $table->time('start_time');
            $table->decimal('cost_per_person', 10, 2)->nullable();
            $table->date('registration_deadline')->nullable();
            $table->integer('max_capacity')->nullable();
            $table->enum('status', ['Draft', 'Published', 'In Progress', 'Completed', 'Cancelled'])->default('Draft');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('tour_date');
            $table->index('status');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
