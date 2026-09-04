<?php

namespace App\Http\Resources;

use App\Models\ParentProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Profile-centric parent payload for the staff Parents register — distinct
 * from GuardianResource, which is centred on ONE student↔parent link.
 *
 * @mixin ParentProfile
 */
class ParentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->relationLoaded('user') ? $this->user : null;

        return [
            'id' => $this->id,
            'public_id' => $user?->public_id,
            'name' => $user?->name,
            'first_name' => $this->first_name,
            'father_name' => $this->father_name,
            'grandfather_name' => $this->grandfather_name,
            'phone' => $user?->phone,
            'email' => $user?->email,
            'secondary_phone' => $this->secondary_phone,
            'gender' => $this->gender,
            'occupation' => $this->occupation,
            'employer' => $this->employer,
            'photo_url' => $this->photo_url,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'sub_city' => $this->sub_city,
            'woreda' => $this->woreda,
            'house_no' => $this->house_no,
            'is_verified' => $this->is_verified,
            'children_count' => $this->whenCounted('children_count'),

            // Portal account state. A parent ALWAYS has a users row (created
            // at guardian provisioning), so "no login" here means the setup
            // link was never used — has_password is the discriminator.
            'account' => $this->when($this->relationLoaded('user'), fn (): ?array => $user === null ? null : [
                'status' => $user->status->value,
                'status_label' => $user->status->label(),
                'has_password' => $user->password !== null,
                'last_login_at' => $user->last_login_at,
                'phone' => $user->phone,
            ]),

            'attachments' => $this->whenLoaded(
                'attachments',
                fn () => $this->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'category' => $attachment->category,
                    'url' => $attachment->url(),
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'created_at' => $attachment->created_at,
                ])->values(),
            ),

            'children' => $this->whenLoaded(
                'guardianships',
                fn () => $this->guardianships->map(function ($link): ?array {
                    $student = $link->student;
                    if ($student === null) {
                        return null;
                    }
                    $enrollment = $student->currentEnrollment;

                    return [
                        'student_id' => $student->id,
                        'full_name' => $student->full_name,
                        'public_id' => $student->public_id,
                        'relationship' => $link->relationship->value,
                        'is_primary' => $link->is_primary,
                        'grade_level' => $enrollment?->gradeLevel?->name,
                        'school' => $enrollment?->branch?->school?->name,
                        'branch' => $enrollment?->branch?->name,
                    ];
                })->filter()->values(),
            ),

            'created_at' => $this->created_at,
        ];
    }
}
