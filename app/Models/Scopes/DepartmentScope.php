<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class DepartmentScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Only apply scope if user is authenticated
        if (!Auth::check()) {
            return;
        }

        // Don't apply scope to Department model itself (to avoid circular reference)
        if ($model instanceof \App\Models\Department) {
            return;
        }

        $user = Auth::user();

        // Superadmin and Admin bypass scope - they can see all records
        if ($user->hasRole(['superadmin', 'admin'])) {
            return;
        }

        // Bypass scope for users with cross-department view permission
        $modelTable = $model->getTable();
        if (in_array($modelTable, ['members', 'member_groups', 'groups'])) {
            $permissionMap = [
                'members' => 'members.view_all',
                'member_groups' => 'member_groups.view_all',
                'groups' => 'member_groups.view_all',
            ];
            if ($user->can($permissionMap[$modelTable])) {
                return;
            }
        }

        // Apply department scope for other authenticated users
        if ($user->department_id) {
            $builder->where('department_id', $user->department_id);
        }
    }

    /**
     * Extend the query builder with department scope methods.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutDepartmentScope', function (Builder $builder) {
            $this->remove($builder, $builder->getQuery());
            return $builder;
        });

        $builder->macro('withAllDepartments', function (Builder $builder) {
            return $builder->withoutDepartmentScope();
        });
    }
}
