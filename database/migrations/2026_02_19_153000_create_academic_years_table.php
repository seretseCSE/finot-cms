<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->date('start_date');
            $table->date('end_date');

            $table->boolean('is_active')->default(false);
            $table->enum('status', ['Draft', 'Active', 'Deactivated'])->default('Draft');

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();

            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            $table->index(['status']);
            $table->index(['is_active']);
        });

        // start_date must be before end_date
        try {
            DB::statement('ALTER TABLE academic_years ADD CONSTRAINT chk_academic_year_dates CHECK (start_date < end_date)');
        } catch (\Throwable $e) {
            // Ignore if DB doesn't support check constraints.
        }

        // Enforce only one active row (partial unique index equivalent)
        // MySQL 8 supports functional indexes; for other DBs this may be ignored.
        try {
            DB::statement('CREATE UNIQUE INDEX academic_years_one_active ON academic_years ((CASE WHEN is_active = 1 THEN 1 ELSE NULL END))');
        } catch (\Throwable $e) {
            // Fallback to application-level enforcement.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('DROP INDEX academic_years_one_active ON academic_years');
        } catch (\Throwable $e) {
        }

        Schema::dropIfExists('academic_years');
    }
};
