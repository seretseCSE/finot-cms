<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'preferred_language' => $this->preferred_language,
            'notify_via_sms' => $this->notify_via_sms,
            'notify_via_email' => $this->notify_via_email,
            'notify_via_push' => $this->notify_via_push,
            'avatar_url' => $this->avatarUrl(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'last_login_at' => $this->last_login_at,
            // All derived from memberships (ADR-010). `permissions` is the
            // COARSE global union for client bootstrapping; the frontend narrows
            // it per active context via role_permissions × memberships.
            'roles' => $this->roleNames(),
            'permissions' => $this->allPermissionNames(),
            'role_permissions' => $this->rolePermissionsMap(),
            'memberships' => MembershipResource::collection($this->whenLoaded('memberships')),
            // Relationship-derived hats (never memberships): drive the /me lane.
            'is_parent' => $this->parentProfile()->exists(),
            'is_student' => $this->studentProfile()->exists(),
            // The tutor hat (ADR-012): owning a tutor_profiles row. The
            // status ships too so the workspace can adapt (apply → pending
            // → approved) without an extra request.
            'is_tutor' => ($tutorStatus = $this->tutorProfile()->value('status')) !== null,
            'tutor_status' => $tutorStatus,
        ];
    }
}
