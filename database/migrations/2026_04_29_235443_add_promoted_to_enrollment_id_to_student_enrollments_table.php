<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->foreignId('promoted_to_enrollment_id')
                ->nullable()
                ->after('completed_by')
                ->constrained('student_enrollments')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropForeign(['promoted_to_enrollment_id']);
            $table->dropColumn('promoted_to_enrollment_id');
        });
    }
};
