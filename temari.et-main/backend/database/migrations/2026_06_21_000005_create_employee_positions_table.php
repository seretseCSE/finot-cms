<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One row per JOB the employee holds. `ended_on` null = the position is
 * current. Salary is nullable: a combined salary covering several job titles
 * sits on the PRIMARY position, the others carry null ("compensated under
 * primary"). Job titles come from App\Support\JobTitles; the ones that
 * map to kernel roles keep the matching branch membership in sync
 * (SyncPositionMembershipsAction).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('job_title', 100);
            $table->string('employment_type', 30)->default('full_time');
            $table->unsignedTinyInteger('salary_level')->nullable(); // 1-10, shown as roman I-X
            $table->decimal('salary', 12, 2)->nullable();
            $table->date('hired_on')->nullable();
            $table->date('last_promoted_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('employee_id');
            $table->index(['job_title', 'ended_on']);
        });

        // An employee may hold each job title at most once concurrently.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX employee_positions_unique_active
            ON employee_positions (employee_id, job_title)
            WHERE ended_on IS NULL AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_positions');
    }
};
