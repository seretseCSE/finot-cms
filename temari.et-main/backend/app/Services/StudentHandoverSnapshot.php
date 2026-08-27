<?php

namespace App\Services;

use App\Models\Student;

/**
 * Freezes "the student's file as they left" at the moment a transfer is
 * approved (ADR-017). The sending school's read-only archive view is served
 * from this snapshot — exactly like the frozen local copy a school keeps in
 * multi-database SIS platforms — so nothing the receiving school changes
 * afterwards (address, health, guardians, documents) ever leaks backward.
 */
class StudentHandoverSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function capture(Student $student): array
    {
        $student->loadMissing([
            'healthConditions',
            'attachments.branch:id,name',
            'attachments.uploader:id,name',
            'guardians.parentProfile.user',
        ]);

        return [
            'captured_at' => now()->toIso8601String(),

            // Address + contact block — the profile fields the archive's
            // Address tab renders. Identity (name, DOB…) stays live: it is
            // the same person, not era-bound data.
            'profile' => [
                'primary_phone' => $student->primary_phone,
                'email' => $student->email,
                'country' => $student->country,
                'state' => $student->state,
                'city' => $student->city,
                'sub_city' => $student->sub_city,
                'woreda' => $student->woreda,
                'house_no' => $student->house_no,
                'birth_country' => $student->birth_country,
                'birth_state' => $student->birth_state,
                'birth_city' => $student->birth_city,
                'birth_sub_city' => $student->birth_sub_city,
                'birth_woreda' => $student->birth_woreda,
            ],

            'health' => [
                'blood_type' => $student->blood_type,
                'health_notes' => $student->health_notes,
                'conditions' => $student->healthConditions->map(fn ($condition): array => [
                    'health_condition_id' => $condition->id,
                    'name' => $condition->name,
                    'category' => $condition->category->value,
                    'severity' => $condition->pivot->severity,
                    'notes' => $condition->pivot->notes,
                    'medication' => $condition->pivot->medication,
                ])->values()->all(),
            ],

            // The family as it was on file — GuardianResource-shaped so the
            // guardians endpoint can serve it verbatim to archive viewers.
            'guardians' => $student->guardians->map(function ($guardian): array {
                $profile = $guardian->parentProfile;
                $user = $profile?->user;

                return [
                    'id' => $guardian->id,
                    'student_id' => $guardian->student_id,
                    'parent_id' => $guardian->parent_id,
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
                    'photo_url' => null,
                    'account' => null,
                    'relationship' => $guardian->relationship->value,
                    'relationship_label' => $guardian->relationship->label(),
                    'is_primary' => $guardian->is_primary,
                    'emergency_contact' => $guardian->emergency_contact,
                    'priority_order' => $guardian->priority_order,
                    'is_active' => $guardian->is_active,
                    'notes' => $guardian->notes,
                    'created_at' => $guardian->created_at?->toIso8601String(),
                ];
            })->values()->all(),

            // Documents on file at departure — ids anchor the archive's
            // era filter; metadata survives even if a row is later deleted.
            'attachments' => $student->attachments->map(fn ($attachment): array => [
                'id' => $attachment->id,
                'name' => $attachment->name,
                'category' => $attachment->category,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'branch_name' => $attachment->branch?->name,
                'created_at' => $attachment->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
