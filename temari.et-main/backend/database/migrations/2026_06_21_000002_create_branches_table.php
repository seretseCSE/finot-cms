<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branches are the real tenant boundary. Every operational record is scoped to a
 * branch. `code` is the Ministry-assigned school code, unique per branch.
 * Geo coordinates are temari-admin-only and never exposed to school staff.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('country')->default('Ethiopia');
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('sub_city')->nullable();
            $table->string('woreda')->nullable();
            $table->string('house_no')->nullable();
            // Official branch phone for document mastheads — falls back to
            // the school phone, then the principal's, when empty.
            $table->string('phone', 20)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            // Branch-level configuration (attendance_mode, grading prefs, ...).
            // Config only - never store queryable domain data here.
            $table->json('settings')->nullable();
            // Monotonic official-receipt sequence (RCT-{code}-{seq}), bumped
            // under row lock by RecordPaymentAction. Never reset.
            $table->unsignedBigInteger('receipt_counter')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // School → branches is the most common scoping lookup.
            $table->index('school_id');
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index branches_code_unique'
            .' on branches (code) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
