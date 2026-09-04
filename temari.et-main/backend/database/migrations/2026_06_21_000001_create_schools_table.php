<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A school is only an identity holder. All operational data (students, staff,
 * enrollments, fees) hangs off its branches, never off the school directly.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // Official school logo (R2 path, signed URLs). Managed exclusively
            // by Temari.et platform staff — schools request changes, they
            // never self-serve (it appears on official documents).
            $table->string('logo_path')->nullable();
            // Official contact line for document mastheads (transcripts,
            // report cards). Branch values win; these are the school-wide
            // fallback before the principal's own phone.
            $table->string('phone', 20)->nullable();
            $table->string('address')->nullable();
            // School-wide academic policy knobs (registration_gate: soft|hard,
            // promotion_threshold: 0–100). Read through the School accessors,
            // never raw — defaults live in code so absent keys stay valid.
            $table->jsonb('settings')->nullable();
            // School Plan AI entitlement: School AI (leadership analytics +
            // teacher copilot) is active while this date is in the future.
            // Granted/extended by Temari.et platform staff (schools pay the
            // plan invoice offline) or by a paid school_plan gateway
            // transaction — never self-served by the school.
            $table->date('ai_plan_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
