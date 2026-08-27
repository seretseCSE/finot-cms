<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programs a branch runs (regular / evening / distance / special). Every branch
 * gets a default `regular` program on creation. Enrollments anchor to a program
 * so DUAL ENROLLMENT works: the same student may hold one active enrollment per
 * program per year (e.g. regular + evening simultaneously) — a decided v1
 * capability School-X never modeled cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type')->default('regular'); // regular|evening|distance|special
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'type']);
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index school_programs_branch_id_name_unique'
            .' on school_programs (branch_id, name) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('school_programs');
    }
};
