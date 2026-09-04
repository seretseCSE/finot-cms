<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * THE authoritative (and only) record of *where* a user holds a role (ADR-010).
 *  - platform scope: school_id & branch_id null (super_admin, temari staff)
 *  - school scope:   school_id set, branch_id null (principal, school_admin)
 *  - branch scope:   school_id & branch_id set (director, registrar, teacher, ...)
 *
 * Relationship roles (student/parent/tutor/vendor) are NEVER stored here —
 * their access derives from students / student_guardians / tutor engagements.
 *
 * Uniqueness must be enforced per scope shape: a plain composite unique treats
 * NULLs as distinct in Postgres, silently allowing duplicate platform/school
 * rows — hence the three partial unique indexes. They deliberately include
 * soft-deleted rows so the restore-on-reassign flow keeps working.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role');
            $table->string('scope'); // platform | school | branch
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'branch_id']);
            $table->index('role');
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX memberships_unique_platform
            ON memberships (user_id, role)
            WHERE school_id IS NULL AND branch_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX memberships_unique_school
            ON memberships (user_id, school_id, role)
            WHERE school_id IS NOT NULL AND branch_id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX memberships_unique_branch
            ON memberships (user_id, branch_id, role)
            WHERE branch_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
