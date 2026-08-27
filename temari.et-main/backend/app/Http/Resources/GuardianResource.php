<?php

namespace App\Http\Resources;

use App\Models\StudentGuardian;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudentGuardian
 */
class GuardianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->parentProfile;
        $user = $profile?->user;

        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'parent_id' => $this->parent_id,
            'public_id' => $user?->public_id,
            'name' => $user?->name,
            'first_name' => $profile?->first_name,
            'father_name' => $profile?->father_name,
            'grandfather_name' => $profile?->grandfather_name,
            'phone' => $user?->phone,
            'email' => $user?->email,
            'secondary_phone' => $profile?->secondary_phone,
            'gender' => $profile?->gender,
            'occupation' => $profile?->occupation,
            'employer' => $profile?->employer,
            'photo_url' => $profile?->photo_url,
            'country' => $profile?->country,
            'state' => $profile?->state,
            'city' => $profile?->city,
            'sub_city' => $profile?->sub_city,
            'woreda' => $profile?->woreda,
            'house_no' => $profile?->house_no,
            'attachments' => $this->when(
                $profile !== null && $profile->relationLoaded('attachments'),
                fn () => $profile->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'category' => $attachment->category,
                    'url' => $attachment->url(),
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'created_at' => $attachment->created_at,
                ])->values(),
            ),
            // Portal account status — every provisioned parent has a user; this
            // answers "can this parent log in?" right on the guardian card.
            'account' => $user === null ? null : [
                'status' => $user->status->value,
                'status_label' => $user->status->label(),
                'has_password' => $user->password !== null,
                'last_login_at' => $user->last_login_at,
            ],
            'relationship' => $this->relationship->value,
            'relationship_label' => $this->relationship->label(),
            'can_view_grades' => $this->can_view_grades,
            'can_view_attendance' => $this->can_view_attendance,
            'can_pay_fees' => $this->can_pay_fees,
            'can_receive_sms' => $this->can_receive_sms,
            'is_primary' => $this->is_primary,
            'emergency_contact' => $this->emergency_contact,
            'priority_order' => $this->priority_order,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
