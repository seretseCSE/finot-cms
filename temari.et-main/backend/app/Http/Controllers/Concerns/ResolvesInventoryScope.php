<?php

namespace App\Http\Controllers\Concerns;

use App\Models\School;
use Illuminate\Http\Request;

/**
 * Shared scope resolution for the inventory module: the school whose
 * inventory the caller is working in (the active branch's school, else the
 * school-wide context), gated on holding ANY of the given permissions in the
 * validated context. Row-level writes still re-check hasPermissionForScope.
 */
trait ResolvesInventoryScope
{
    /**
     * @param  list<string>  $anyOf
     */
    protected function inventorySchool(Request $request, array $anyOf): School
    {
        $user = $request->user();

        abort_unless(collect($anyOf)->contains(fn (string $p) => $user->hasContextPermission($p)), 403);

        $branch = $this->activeBranchOrNull($request);
        $schoolId = $branch?->school_id ?? $this->activeSchoolScopeId($request);

        abort_if($schoolId === null, 422, 'Select a school context first.');

        return School::findOrFail($schoolId);
    }
}
