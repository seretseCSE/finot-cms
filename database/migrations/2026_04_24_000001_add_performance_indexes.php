<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // members(phone) — already covered by members_phone_index & members_phone_unique
        // tour_passengers(tour_id, phone) — already covered by tour_passengers_tour_id_phone_unique
        // contributions(member_id, academic_year_id, month) — already covered by contributions_member_year_month_unique

        // student_attendances: composite index for session-based lookups
        if (! $this->hasIndex('student_attendances', 'sa_session_student_index')) {
            Schema::table('student_attendances', function (Blueprint $table) {
                $table->index(['session_id', 'student_id'], 'sa_session_student_index');
            });
        }

        // attendance_sessions: composite index for class + academic year lookups
        if (! $this->hasIndex('attendance_sessions', 'as_class_year_index')) {
            Schema::table('attendance_sessions', function (Blueprint $table) {
                $table->index(['class_id', 'academic_year_id'], 'as_class_year_index');
            });
        }

        // audit_logs: replace the existing (entity_type, entity_id) index with a covering
        // index that also includes created_at for time-range filtered entity queries.
        if ($this->hasIndex('audit_logs', 'audit_logs_entity_type_entity_id_index')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex('audit_logs_entity_type_entity_id_index');
            });
        }

        if (! $this->hasIndex('audit_logs', 'al_entity_created_at_index')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index(['entity_type', 'entity_id', 'created_at'], 'al_entity_created_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropIndexIfExists('sa_session_student_index');
        });

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndexIfExists('as_class_year_index');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndexIfExists('al_entity_created_at_index');

            if (! $this->hasIndex('audit_logs', 'audit_logs_entity_type_entity_id_index')) {
                $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_type_entity_id_index');
            }
        });
    }

    /**
     * Check whether a table already has an index by name.
     */
    protected function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list({$table})"))
                ->contains(fn (\stdClass $row): bool => $row->name === $index);
        }

        return collect(DB::select("SHOW INDEX FROM `{$table}`"))
            ->contains(fn (\stdClass $row): bool => $row->Key_name === $index);
    }
};
