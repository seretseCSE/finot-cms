<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class HasDepartmentScope implements Scope
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

        $user = Auth::user();

        // Superadmin and Admin can see all records
        if ($user->hasRole(['superadmin', 'admin'])) {
            return;
        }

        // HR Head can see all members and groups
        if ($user->hasRole('hr_head') && in_array($model->getTable(), ['members', 'groups'])) {
            return;
        }

        // Apply department scope for other roles
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
