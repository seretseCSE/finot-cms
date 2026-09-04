<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ReplaceContactAction;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Rules\EthiopianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchContactController extends Controller
{
    /**
     * Replace a branch's director. Deactivates the current director and
     * provisions the new person (SMS set-password link).
     */
    public function update(Request $request, Branch $branch, ReplaceContactAction $action): JsonResponse
    {
        $this->authorize('manageDirector', $branch);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', new EthiopianPhone()],
        ]);

        $actor = $request->user();
        $role = Role::Director;

        // Director must rank strictly below the actor (hierarchy): a director
        // can't appoint another director; principals and platform staff can.
        // Authority is scoped to this branch's school, never a role held elsewhere.
        $actorLevel = $actor->authorityLevelFor($branch->school_id, $branch->id);
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
            school: $branch->school,
            branch: $branch,
        );

        return (new BranchResource($branch->load(['school:id,name', 'directorMembership.user'])))
            ->additional(['message' => "Director updated for {$branch->name}."])
            ->response();
    }
}
