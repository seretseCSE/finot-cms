<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Step 1: Add teacher_assignment_id if not exists
        if (! Schema::hasColumn('teacher_attendance', 'teacher_assignment_id')) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                $table->foreignId('teacher_assignment_id')->nullable()->after('session_id')->constrained('teacher_assignments')->cascadeOnDelete();
            });
        }

        // Step 2: Backfill any null teacher_assignment_id values
        DB::statement("
            UPDATE teacher_attendance
            SET teacher_assignment_id = (
                SELECT ta2.id
                FROM teacher_assignments ta2
                INNER JOIN session_teacher_assigns sta ON sta.teacher_assignment_id = ta2.id
                WHERE ta2.teacher_id = teacher_attendance.teacher_id
                  AND sta.session_id = teacher_attendance.session_id
                LIMIT 1
            )
            WHERE teacher_assignment_id IS NULL
        ");

        // Step 3: Make teacher_assignment_id non-nullable
        Schema::table('teacher_attendance', function (Blueprint $table) {
            $table->foreignId('teacher_assignment_id')->nullable(false)->change();
        });

        // Step 4: Drop substitute_teacher_name if exists
        if (Schema::hasColumn('teacher_attendance', 'substitute_teacher_name')) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                $table->dropColumn('substitute_teacher_name');
            });
        }

        // Step 5: Drop the old unique index if it exists, handling MySQL FK constraint issue
        $indexes = Schema::getIndexes('teacher_attendance');
        $hasOldUnique = collect($indexes)->contains('name', 'ta_teacher_session_unique');

        if ($hasOldUnique) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                // Drop FK on teacher_id so we can drop the unique index
                $table->dropForeign(['teacher_id']);
                $table->dropUnique('ta_teacher_session_unique');
                // Re-add FK (MySQL will create a plain index for it)
                $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            });
        }

        // Step 6: Add new unique index if not exists
        $hasNewUnique = collect($indexes)->contains('name', 'ta_assignment_session_unique');
        if (! $hasNewUnique) {
            Schema::table('teacher_attendance', function (Blueprint $table) {
                $table->unique(['teacher_assignment_id', 'session_id'], 'ta_assignment_session_unique');
            });
        }

        // Step 7: Update session_outcome enum (MySQL only)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE teacher_attendance MODIFY session_outcome ENUM('Normal', 'Cancelled') DEFAULT 'Normal'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE teacher_attendance MODIFY session_outcome ENUM('Normal', 'Cancelled', 'Substitute_Assigned') DEFAULT 'Normal'");
        }

        Schema::table('teacher_attendance', function (Blueprint $table) {
            $table->dropUnique('ta_assignment_session_unique');
            $table->unique(['teacher_id', 'session_id'], 'ta_teacher_session_unique');
            $table->string('substitute_teacher_name', 255)->nullable();
            $table->dropForeign(['teacher_assignment_id']);
            $table->dropColumn('teacher_assignment_id');
        });
    }
};
