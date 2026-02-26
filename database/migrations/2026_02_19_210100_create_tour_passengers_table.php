<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_passengers', function (Blueprint $table) {
            $table->id();
            $table->string('passenger_code', 20)->unique(); // TP-000001 format
            $table->foreignId('tour_id')->constrained('tours')->onDelete('cascade');
            $table->string('full_name', 255);
            $table->string('phone', 20);
            $table->integer('passenger_count')->default(1);
            $table->string('receipt_image', 500)->nullable();
            $table->foreignId('member_id')->nullable()->constrained('members')->onDelete('set null');
            $table->enum('registration_type', ['Public', 'Internal'])->default('Public');
            $table->enum('status', ['Pending', 'Confirmed', 'Cancelled'])->default('Pending');
            $table->date('registration_date');
            $table->foreignId('registered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            // Unique constraint: same phone cannot register twice for same tour
            $table->unique(['tour_id', 'phone']);

            // Indexes
            $table->index('tour_id');
            $table->index('phone');
            $table->index('member_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_passengers');
    }
};
