<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'school_id' => $this->school_id,
            'branch_id' => $this->branch_id,
            'first_name' => $this->first_name,
            'father_name' => $this->father_name,
            'grandfather_name' => $this->grandfather_name,
            'mother_name' => $this->mother_name,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'national_student_id' => $this->national_student_id,
            'primary_phone' => $this->primary_phone,
            'email' => $this->email,
            'citizenship' => $this->citizenship,
            'marital_status' => $this->marital_status,
            'photo_url' => $this->photo_url,
            'languages' => $this->languages ?? [],
            'birth_country' => $this->birth_country,
            'birth_state' => $this->birth_state,
            'birth_city' => $this->birth_city,
            'birth_sub_city' => $this->birth_sub_city,
            'birth_woreda' => $this->birth_woreda,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'sub_city' => $this->sub_city,
            'woreda' => $this->woreda,
            'house_no' => $this->house_no,
            'is_active' => $this->is_active,

            // Health data is SENSITIVE: only serialized when the detail
            // endpoint eager-loads it — never present in list payloads.
            'blood_type' => $this->when($this->relationLoaded('healthConditions'), $this->blood_type),
            'health_notes' => $this->when($this->relationLoaded('healthConditions'), $this->health_notes),
            'health_conditions' => $this->whenLoaded(
                'healthConditions',
                fn () => $this->healthConditions->map(fn ($condition): array => [
                    'health_condition_id' => $condition->id,
                    'name' => $condition->name,
                    'category' => $condition->category->value,
                    'severity' => $condition->pivot->severity,
                    'notes' => $condition->pivot->notes,
                    'medication' => $condition->pivot->medication,
                ])->values(),
            ),

            'attachments' => $this->whenLoaded(
                'attachments',
                fn () => $this->attachments->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->name,
                    'category' => $attachment->category,
                    'url' => $attachment->url(),
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    // Provenance — only the adding school may delete (ADR-017).
                    'school_id' => $attachment->school_id,
                    'branch_name' => $attachment->relationLoaded('branch') ? $attachment->branch?->name : null,
                    'uploaded_by_name' => $attachment->relationLoaded('uploader') ? $attachment->uploader?->name : null,
                    'created_at' => $attachment->created_at,
                ])->values(),
            ),

            'current_enrollment' => $this->whenLoaded(
                'currentEnrollment',
                fn () => $this->currentEnrollment
                    ? new StudentEnrollmentResource($this->currentEnrollment)
                    : null,
            ),
            'enrollments' => StudentEnrollmentResource::collection($this->whenLoaded('enrollments')),
            'guardians' => GuardianResource::collection($this->whenLoaded('guardians')),
            // Portal account (the student's OWN login, if provisioned): status +
            // last sign-in, so staff can answer "can this student log in?" from
            // the record itself. Null user_id = no login was ever created.
            'account' => $this->when($this->relationLoaded('user'), fn (): ?array => $this->user === null ? null : [
                'status' => $this->user->status->value,
                'status_label' => $this->user->status->label(),
                // Distinguishes "invited, setup link never used" from a live
                // account that simply hasn't signed in since.
                'has_password' => $this->user->password !== null,
                'last_login_at' => $this->user->last_login_at,
                'phone' => $this->user->phone,
                // Phone-less accounts sign in with the student's Temari ID + PIN.
                'login_mode' => $this->user->phone !== null ? 'phone' : 'student_id',
            ]),
            'school_name' => $this->whenLoaded('branch', fn () => $this->branch?->school?->name),
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch?->name),
            // 'full' (live custody in the viewer's context) or 'archive'
            // (former school: read-only, forward-blind). Set by the controller
            // — see StudentController@show / @index.
            'access' => $this->when($this->getAttribute('viewer_access') !== null, fn () => $this->viewer_access),
            // Archive-only viewers: the era snapshot (address/health as the
            // student LEFT this school) — ADR-017 handover snapshot.
            'archive' => $this->when($this->getAttribute('archive_payload') !== null, fn () => $this->archive_payload),
            // Transfer supporting documents for participant schools only.
            'transfer_files' => $this->when(
                $this->getAttribute('transfer_files_payload') !== null,
                fn () => $this->transfer_files_payload,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
