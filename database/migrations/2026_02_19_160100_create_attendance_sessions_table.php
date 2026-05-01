<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->date('session_date');
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            $table->enum('status', ['Open', 'Completed', 'Locked'])->default('Open');

            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('unlock_justification')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('substitute_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->text('substitute_notes')->nullable();

            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['session_date', 'academic_year_id'], 'as_date_year_unique');
            $table->index(['class_id', 'session_date']);
            $table->index(['class_id', 'academic_year_id'], 'as_class_year_index');
            $table->index(['academic_year_id', 'status']);
        });

        // Pivot table: session_classes (multi-class support)
        Schema::create('session_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unique(['session_id', 'class_id'], 'sc_session_class_unique');
        });

        // Pivot table: session_teacher_assigns
        Schema::create('session_teacher_assigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->foreignId('teacher_assignment_id')->constrained('teacher_assignments')->cascadeOnDelete();
            $table->unique(['session_id', 'teacher_assignment_id'], 'sta_session_ta_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_teacher_assigns');
        Schema::dropIfExists('session_classes');
        Schema::dropIfExists('attendance_sessions');
    }
};
