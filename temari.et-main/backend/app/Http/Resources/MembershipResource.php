<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\Membership;
use App\Support\FinanceControls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Membership
 */
class MembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role instanceof Role ? $this->role : Role::tryFrom((string) $this->role);

        return [
            'id' => $this->id,
            'role' => $this->role,
            'role_label' => $role?->label(),
            'scope' => $this->scope,
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'is_active' => $this->is_active,
            // Director finance gate: mirrors the kernel so the client-side
            // effective-permission derivation strips the same set.
            'director_finance_access' => $this->when(
                $role === Role::Director && $this->school_id !== null,
                fn (): bool => FinanceControls::directorAccess((int) $this->school_id),
            ),
        ];
    }
}
