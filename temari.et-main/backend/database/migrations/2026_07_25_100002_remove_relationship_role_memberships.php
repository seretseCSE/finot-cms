<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Relationship roles (student, parent, tutor, vendor) are NEVER granted via
 * memberships (ADR-010/012) — the relationship lane derives from profile links
 * (student_guardians, students.user_id, tutor_profiles). An older DemoSeeder
 * created global `parent` membership rows anyway, which made the client treat
 * every parent as staff (any active membership counts as a staff hat) and
 * merged the staff and family workspaces. Purge every such row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('memberships')
            ->whereIn('role', ['student', 'parent', 'tutor', 'vendor'])
            ->delete();
    }

    public function down(): void
    {
        // Data cleanup of rows that should never have existed — nothing to
        // restore. Intentionally a no-op.
    }
};
