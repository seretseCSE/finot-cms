<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branch facilities the timetable can book: ordinary classrooms plus shared
 * special rooms (lab, gym, library, ICT…). Sections keep their own home room
 * (sections.room_number); a timetable slot only references a `rooms` row when
 * the lesson happens somewhere special — the solver treats those as an
 * exclusive resource per (day, period).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            // classroom|lab|library|ict|gym|music|art|hall|other
            $table->string('type', 20)->default('classroom');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'type']);
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index rooms_branch_id_name_unique'
            .' on rooms (branch_id, name) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
