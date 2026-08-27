<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReplaceContactAction;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use App\Rules\EthiopianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SchoolContactController extends Controller
{
    /**
     * Replace a school-scoped contact (principal / school_admin). Deactivates the
     * current holder and provisions the new person (SMS set-password link).
     */
    public function update(Request $request, School $school, ReplaceContactAction $action): JsonResponse
    {
        $this->authorize('manageContacts', $school);

        $data = $request->validate([
            'role' => ['required', Rule::in([Role::Principal->value, Role::SchoolAdmin->value])],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new EthiopianPhone],
        ]);

        $actor = $request->user();
        $role = Role::from($data['role']);

        // Role must rank strictly below the actor (hierarchy): school-level
        // contacts (principal / school_admin, level 1) can only be managed by
        // Temari platform staff (level 0). Authority is scoped to this school.
        $actorLevel = $actor->authorityLevelFor($school->id, null);
        abort_unless(
            $actor->isPlatformUser() || ($actorLevel !== null && $actorLevel < $role->hierarchyLevel()),
            403,
            'You cannot manage this contact.',
        );

        $action->execute(
            name: $data['name'],
            phone: $data['phone'],
            role: $role,
            actor: $actor,
            school: $school,
        );

        return (new SchoolResource(
            $school->loadCount('branches')->load('contactMemberships.user'),
        ))
            ->additional(['message' => "{$role->label()} updated for {$school->name}."])
            ->response();
    }
}
