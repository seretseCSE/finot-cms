<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links a student to a parent/guardian, with per-relationship permissions that
 * gate what the guardian may see/do (grades, attendance, fees) and how they are
 * notified. `is_primary` marks the main contact (one per student).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('student_guardians', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->string('relationship');
            $table->boolean('can_view_grades')->default(true);
            $table->boolean('can_view_attendance')->default(true);
            $table->boolean('can_pay_fees')->default(true);
            $table->boolean('can_receive_sms')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->boolean('emergency_contact')->default(false);
            $table->unsignedTinyInteger('priority_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('student_id');
            // Parent → children is the /me lane's first lookup on every request.
            $table->index('parent_id');
        });

        // Partial unique: trashed rows must not block reusing the value
        // (soft deletes + a plain unique = SQLSTATE 23505 on recreate).
        DB::statement(
            'create unique index student_guardians_student_id_parent_id_unique'
            .' on student_guardians (student_id, parent_id) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardians');
    }
};
