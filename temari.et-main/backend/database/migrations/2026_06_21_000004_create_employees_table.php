<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HR core: the PERSON's employment profile at a branch (identity, personal
 * data, address). The JOB(S) live in `employee_positions` — a staff member may
 * hold several job titles at once (ADR-011 refined). School-level staff
 * (principal/school_admin) have branch_id null. Names use the Ethiopian
 * patronymic convention; gender feeds maternity/paternity leave eligibility
 * and staff-composition reports. check_in/check_out are the person's expected
 * daily attendance window.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            // Nullable: support staff outside the school's account policy have an
            // HR file but no login (accounts are settings-gated per job title).
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();

            // Patronymic identity
            $table->string('first_name');
            $table->string('father_name')->nullable();
            $table->string('grandfather_name')->nullable();
            $table->string('gender', 10)->nullable(); // male|female
            $table->string('phone')->nullable();
            $table->string('photo_path')->nullable();

            // Personal
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('nationality')->nullable()->default('Ethiopia');

            // Address
            $table->string('country')->default('Ethiopia');
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('sub_city')->nullable();
            $table->string('woreda')->nullable();
            $table->string('house_no')->nullable();

            // Career-level facts that belong to the person, not a position.
            $table->string('professional_level')->nullable();
            $table->date('retirement_on')->nullable();
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'branch_id']);
            // Staff self-service + ownership checks resolve employees by user_id.
            $table->index('user_id');

            // Flat haystack for the global (⌘K) search — see students migration.
            $table->text('search_text')->storedAs(
                'first_name'
                ." || ' ' || coalesce(father_name, '')"
                ." || ' ' || coalesce(grandfather_name, '')"
                ." || ' ' || coalesce(phone, '')"
                ." || ' ' || coalesce(regexp_replace(phone, '\\D', '', 'g'), '')"
                ." || ' ' || coalesce(email, '')",
            );
        });

        DB::statement('CREATE INDEX employees_search_text_trgm ON employees USING gin (search_text gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
