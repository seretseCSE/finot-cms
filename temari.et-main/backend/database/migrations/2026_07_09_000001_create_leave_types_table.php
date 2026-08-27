<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave TYPES are SCHOOL-owned policy (like bank accounts) — one catalog
 * shared by every branch, auto-provisioned from the Ethiopian Labour
 * Proclamation 1156/2019 defaults (App\Support\LeavePolicy) the first time a
 * school touches leave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            // Slug for the seeded statutory types (annual, sick, maternity…);
            // null for school-defined custom types.
            $table->string('code', 40)->nullable();
            $table->string('name', 100);
            // Entitlement per leave year; null = no fixed cap (tracked only).
            $table->decimal('days_per_year', 5, 1)->nullable();
            // Annual leave grows with service (Art. 77: +1 working day per 2
            // extra years). entitled = days_per_year + floor(service/every)*bonus.
            $table->unsignedTinyInteger('service_bonus_days')->default(0);
            $table->unsignedTinyInteger('service_bonus_every_years')->default(0);
            $table->boolean('is_paid')->default(true);
            // Maternity / paternity only apply to one gender.
            $table->string('applicable_gender', 10)->nullable(); // female|male|null
            $table->boolean('requires_note')->default(false); // e.g. sick → medical certificate ref
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
