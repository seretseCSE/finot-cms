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
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            $table->date('assigned_date')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->enum('assignment_status', ['Active', 'Inactive', 'On Leave'])->default('Active');
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            $table->index(['teacher_id', 'academic_year_id']);
            $table->index(['class_id', 'academic_year_id']);
            $table->index(['effective_to', 'assignment_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
