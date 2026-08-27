<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A timetable is drafted, tuned, then published — never edited live in one
 * copy. Slots hang off a version; the solver fills a draft (status
 * `generating` while the queued job runs), staff hand-adjust and lock slots,
 * and publishing archives the previously published version. At most one
 * published version per term (partial unique). `score`/`conflicts` are the
 * solver's soft-penalty total and unplaced-activity report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('status', 12)->default('draft'); // draft|generating|published|archived
            $table->unsignedInteger('score')->nullable();
            $table->jsonb('conflicts')->default('[]');
            // Days the branch teaches, e.g. [1,2,3,4,5] (+6 for Saturday schools).
            $table->jsonb('days')->default('[1,2,3,4,5]');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['term_id', 'status']);
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX timetable_versions_one_published
            ON timetable_versions (term_id)
            WHERE status = 'published' AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_versions');
    }
};
