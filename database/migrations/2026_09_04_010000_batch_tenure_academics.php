<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('batches')) {
            Schema::create('batches', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->unsignedSmallInteger('start_year')->nullable();
                $table->unsignedTinyInteger('tenure_years')->default(4);
                $table->string('status', 20)->default('open'); // open|closed
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('status');
                $table->unique('name');
            });
        }

        if (! Schema::hasTable('batch_years')) {
            Schema::create('batch_years', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
                $table->unsignedTinyInteger('program_year');
                $table->string('name', 120);
                $table->string('status', 20)->default('planned'); // planned|active|completed
                $table->timestamps();

                $table->unique(['batch_id', 'program_year']);
                $table->index('status');
            });
        }

        Schema::table('terms', function (Blueprint $table) {
            if (! Schema::hasColumn('terms', 'batch_year_id')) {
                $table->foreignId('batch_year_id')->nullable()->after('academic_year_id')->constrained('batch_years')->nullOnDelete();
            }
            if (! Schema::hasColumn('terms', 'status')) {
                $table->string('status', 20)->default('planned')->after('is_active'); // planned|active|closed
                $table->index('status');
            }
        });

        Schema::table('student_enrollments', function (Blueprint $table) {
            if (! Schema::hasColumn('student_enrollments', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('academic_year_id')->constrained('batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('student_enrollments', 'batch_year_id')) {
                $table->foreignId('batch_year_id')->nullable()->after('batch_id')->constrained('batch_years')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('subject_offerings')) {
            Schema::create('subject_offerings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_year_id')->constrained('batch_years')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
                $table->unsignedSmallInteger('max_score')->default(100);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['term_id', 'subject_id', 'class_id'], 'subject_offerings_term_subject_class_unique');
                $table->index(['batch_year_id', 'term_id']);
            });
        }

        if (! Schema::hasTable('assessments')) {
            Schema::create('assessments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_offering_id')->constrained('subject_offerings')->cascadeOnDelete();
                $table->string('name', 120);
                $table->unsignedSmallInteger('max_score')->default(100);
                $table->decimal('weight', 8, 2)->default(100);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_open')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['subject_offering_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('assessment_scores')) {
            Schema::create('assessment_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->decimal('score', 8, 2)->nullable();
                $table->boolean('is_absent')->default(false);
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['assessment_id', 'member_id']);
            });
        }

        if (! Schema::hasTable('subject_credits')) {
            Schema::create('subject_credits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('source_batch_year_id')->nullable()->constrained('batch_years')->nullOnDelete();
                $table->foreignId('source_term_id')->nullable()->constrained('terms')->nullOnDelete();
                $table->decimal('score', 8, 2)->nullable();
                $table->unsignedSmallInteger('max_score')->nullable();
                $table->string('status', 20)->default('passed'); // passed|transferred
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['member_id', 'subject_id', 'source_batch_year_id'], 'subject_credits_member_subject_source_unique');
            });
        }

        if (! Schema::hasTable('student_term_results')) {
            Schema::create('student_term_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
                $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();
                $table->foreignId('batch_year_id')->nullable()->constrained('batch_years')->nullOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->foreignId('enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
                $table->decimal('total', 10, 2)->nullable();
                $table->decimal('average', 8, 2)->nullable();
                $table->unsignedInteger('rank')->nullable();
                $table->unsignedInteger('rank_of')->nullable();
                $table->json('breakdown')->nullable();
                $table->timestamp('computed_at')->nullable();
                $table->foreignId('computed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['member_id', 'term_id']);
                $table->index(['term_id', 'batch_year_id']);
            });
        }

        // Allow one Enrolled row per member+year; Promoted/Completed/Withdrawn may share the year.
        try {
            \Illuminate\Support\Facades\DB::statement('DROP INDEX IF EXISTS se_one_enrolled_per_year');
        } catch (\Throwable) {
            //
        }

        try {
            \Illuminate\Support\Facades\DB::statement(
                "CREATE UNIQUE INDEX se_one_enrolled_per_year ON student_enrollments (member_id, academic_year_id) WHERE status = 'Enrolled'"
            );
        } catch (\Throwable) {
            // MySQL may need a different approach; ignore if unsupported in this driver.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_term_results');
        Schema::dropIfExists('subject_credits');
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('subject_offerings');

        Schema::table('student_enrollments', function (Blueprint $table) {
            if (Schema::hasColumn('student_enrollments', 'batch_year_id')) {
                $table->dropConstrainedForeignId('batch_year_id');
            }
            if (Schema::hasColumn('student_enrollments', 'batch_id')) {
                $table->dropConstrainedForeignId('batch_id');
            }
        });

        Schema::table('terms', function (Blueprint $table) {
            if (Schema::hasColumn('terms', 'batch_year_id')) {
                $table->dropConstrainedForeignId('batch_year_id');
            }
            if (Schema::hasColumn('terms', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::dropIfExists('batch_years');
        Schema::dropIfExists('batches');
    }
};
