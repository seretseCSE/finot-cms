<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        try {
            DB::statement('DROP INDEX IF EXISTS se_one_enrolled_per_year');
        } catch (\Throwable) {
            //
        }

        try {
            Schema::table('student_enrollments', function (Blueprint $table) {
                $table->dropUnique('se_member_class_year_unique');
            });
        } catch (\Throwable) {
            try {
                DB::statement('DROP INDEX IF EXISTS se_member_class_year_unique');
            } catch (\Throwable) {
                //
            }
        }

        if ($driver === 'sqlite') {
            try {
                DB::statement(
                    "CREATE UNIQUE INDEX se_one_enrolled_per_year ON student_enrollments (member_id, academic_year_id) WHERE status = 'Enrolled'"
                );
            } catch (\Throwable) {
                //
            }

            try {
                DB::statement(
                    "CREATE UNIQUE INDEX se_member_class_year_enrolled_unique ON student_enrollments (member_id, class_id, academic_year_id) WHERE status = 'Enrolled'"
                );
            } catch (\Throwable) {
                //
            }
        }
    }

    public function down(): void
    {
        try {
            DB::statement('DROP INDEX IF EXISTS se_one_enrolled_per_year');
        } catch (\Throwable) {
            //
        }

        try {
            DB::statement('DROP INDEX IF EXISTS se_member_class_year_enrolled_unique');
        } catch (\Throwable) {
            //
        }
    }
};
