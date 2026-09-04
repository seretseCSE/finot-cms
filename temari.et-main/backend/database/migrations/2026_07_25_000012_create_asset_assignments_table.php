<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The custody chain: who holds each asset unit. Exactly one holder FK is set
 * per row (employee, student, room or section — explicit columns, never a
 * JSON blob); the open row (returned_on NULL) is the current holder, and the
 * partial unique guarantees a unit is never in two hands. This is the table
 * clearance reads: "has this person returned everything?"
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('asset_unit_id')->constrained()->restrictOnDelete();
            $table->string('holder_type', 20); // employee|student|room|section
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('assigned_on');
            $table->date('returned_on')->nullable();
            $table->string('return_condition', 20)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'holder_type']);
            $table->index('employee_id');
            $table->index('student_id');
            $table->index('room_id');
            $table->index('section_id');
            $table->index('asset_unit_id');
        });

        // A unit is in exactly one pair of hands at a time.
        DB::statement('CREATE UNIQUE INDEX asset_assignments_open_unique ON asset_assignments (asset_unit_id) WHERE returned_on IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
