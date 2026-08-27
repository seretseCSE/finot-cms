<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The school's teacher-appraisal rubric (MoE continuous performance
 * appraisal format). Auto-provisioned per school with the national default
 * criteria (App\Support\EvaluationPolicy); schools tune labels and weights.
 * Evaluations SNAPSHOT criteria at creation, so editing the template never
 * rewrites history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'is_active']);
        });

        DB::statement(
            'create unique index evaluation_templates_school_name_unique'
            .' on evaluation_templates (school_id, name) where deleted_at is null',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_templates');
    }
};
