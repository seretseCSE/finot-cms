<?php

namespace App\Observers;

use App\Models\Department;

class DepartmentObserver
{
    /**
     * Handle the Department "deleting" event.
     */
    public function deleting(Department $department): void
    {
        // Disallow deletion if active users are still assigned to this department
        if ($department->users()->where('is_active', true)->exists()) {
            throw new \RuntimeException('Cannot delete department with active users.');
        }

        // Nullify department_id for users (soft-deleted or inactive)
        $department->users()->update(['department_id' => null]);
    }
}
