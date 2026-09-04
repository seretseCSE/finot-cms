<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide directory of Ethiopian schools — on Temari or not — used for
 * "previous school" on enrollments and future transfer flows. One searchable
 * catalog instead of two pickers: every Temari school auto-gets a verified row
 * (school_id set), seed data covers well-known schools, and registrars may add
 * missing ones inline as unverified rows (with provenance) for platform staff
 * to verify or merge later.
 *
 * Filename note: suffix `_1` deliberately sorts this between 000018 and 000019
 * so the table exists before student_enrollments adds its FK.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('school_directory', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('zone')->nullable();
            $table->string('city')->nullable();

            // Set when the school is hosted on Temari — auto-maintained.
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();

            $table->boolean('is_verified')->default(false);
            $table->foreignId('created_by_school_id')->nullable()->constrained('schools')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('school_id');
        });

        DB::statement('CREATE INDEX school_directory_name_trgm ON school_directory USING gin (name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('school_directory');
    }
};
