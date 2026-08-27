<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grading scales map raw numeric marks to displayed grades (letter, label,
 * grade points, pass/fail). Platform-seeded defaults (school_id null: the
 * Ethiopian percentage, letter and early-grade descriptive scales) plus
 * optional school-custom rows — the same catalog pattern as subjects. Raw
 * marks stay numeric everywhere; letters are ALWAYS a read-time mapping
 * through a scale, snapshotted into student_term_results on freeze.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_scales', function (Blueprint $table): void {
            $table->id();
            // null school_id = platform-seeded; non-null = school-custom scale.
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index grading_scales_school_id_code_unique'
            .' on grading_scales (school_id, code) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_scales');
    }
};
