<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subjects are platform static data (Ethiopian national curriculum, seeded once
 * with stable codes) plus optional school-custom rows. `category` and the
 * grade applicability set (the grade_level_subject pivot; empty = every grade)
 * power curriculum-driven assignment generation and analytics filters.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20);
            $table->string('name');
            // null school_id = platform-seeded; non-null = school-custom subject
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            // language|mathematics|natural_science|social_science|technology|arts_pe|vocational
            $table->string('category', 30)->nullable();
            // Cognitive load 1 (light — music, PE) … 5 (heavy — maths, physics).
            // The timetable solver prefers heavy subjects in morning periods and
            // keeps them off Friday's last period.
            $table->unsignedTinyInteger('weight')->default(3);
            // Special room this subject teaches in (lab/ict/gym/music/art/hall/
            // library) — the solver books a free room of this type when the
            // branch has one; null = the section's own classroom.
            $table->string('room_type', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('category');
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index subjects_code_unique'
            .' on subjects (code) where deleted_at is null',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
