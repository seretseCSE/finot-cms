<?php

namespace App\Actions;

use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\ConcessionSuggestionService;
use App\Services\RegistrationNotifier;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links a guardian to a student. Two entry paths:
 *
 *  - ATTACH EXISTING: `parent_id` set (found via guardian search by public id
 *    or phone — parents are global persons, so one from another Temari school
 *    is reused, never duplicated);
 *  - CREATE: provisions (or reuses) the user account by phone, upserts the
 *    profile, and texts a password-setup link if the account is new.
 *
 * Either way a parent is never given a school membership — their access is
 * derived from the child's enrollment (ADR-012).
 */
class AddGuardianAction
{
    public function __construct(
        private readonly RegistrationNotifier $notifier,
        private readonly ConcessionSuggestionService $concessionSuggestions,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  Validated by StoreGuardianRequest:
     *                                      `parent_id` OR (`name`/name trio + `phone`), relationship, profile
     *                                      fields (occupation, employer, address…), link flags.
     */
    public function execute(Student $student, array $data): StudentGuardian
    {
        return DB::transaction(function () use ($student, $data): StudentGuardian {
            $isNewAccount = false;

            if (! empty($data['parent_id'])) {
                $parent = ParentProfile::findOrFail((int) $data['parent_id']);
            } else {
                [$parent, $isNewAccount] = $this->provisionParent($data);
            }

            if ($student->guardians()->where('parent_id', $parent->id)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => ['This person is already a guardian of this student.'],
                ]);
            }

            $guardian = $student->guardians()->create([
                'parent_id' => $parent->id,
                'relationship' => $data['relationship'],
                'can_view_grades' => $data['can_view_grades'] ?? true,
                'can_view_attendance' => $data['can_view_attendance'] ?? true,
                'can_pay_fees' => $data['can_pay_fees'] ?? true,
                'can_receive_sms' => $data['can_receive_sms'] ?? true,
                'is_primary' => $data['is_primary'] ?? false,
                'emergency_contact' => $data['emergency_contact'] ?? false,
                'priority_order' => $data['priority_order'] ?? 1,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($guardian->is_primary) {
                $student->guardians()
                    ->whereKeyNot($guardian->id)
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }

            $guardian->load('parentProfile.user');

            // A new guardian link can change what the concession policy sees
            // (sibling counts, staff status) — re-evaluate every live
            // enrollment. Idempotent, and suggestion-only.
            foreach ($student->enrollments()->live()->get() as $enrollment) {
                $this->concessionSuggestions->evaluate($student, $enrollment);
            }

            // Deferred to after-commit inside the notifier — a rolled-back
            // registration never texts anyone.
            $this->notifier->guardianLinked($guardian, $student, $isNewAccount);

            return $guardian;
        });
    }

    /**
     * Find-or-create the user by phone and upsert the parent profile with the
     * given fields. The patronymic trio (when provided) also drives the user's
     * display name.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: ParentProfile, 1: bool}
     */
    private function provisionParent(array $data): array
    {
        $phone = PhoneNumber::normalize((string) $data['phone']) ?? trim((string) $data['phone']);
        $displayName = trim(implode(' ', array_filter([
            $data['first_name'] ?? null,
            $data['father_name'] ?? null,
            $data['grandfather_name'] ?? null,
        ]))) ?: ($data['name'] ?? '');

        $user = User::withTrashed()->firstOrNew(['phone' => $phone]);
        $isNewAccount = ! $user->exists || $user->password === null;

        if (! $user->exists) {
            // The email may already belong to a DIFFERENT account — surface a
            // clear error instead of a unique-constraint 500; the right move
            // is usually attaching that existing person via guardian search.
            $email = $data['email'] ?? null;
            if ($email !== null && User::withTrashed()->where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['This email already belongs to another Temari.et account — use "Find existing" to attach that person instead.'],
                ]);
            }

            $user->fill([
                'name' => $displayName,
                'email' => $email,
                'preferred_language' => 'en',
            ])->save();
        }

        // No role, no membership: being a parent is a RELATIONSHIP, not a
        // grant. The ParentProfile + student_guardians link are the
        // authoritative record; the /me lane derives access from them.
        $profileFields = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'father_name' => $data['father_name'] ?? null,
            'grandfather_name' => $data['grandfather_name'] ?? null,
            'gender' => $data['gender'] ?? null,
            'secondary_phone' => $data['secondary_phone'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'employer' => $data['employer'] ?? null,
            'country' => $data['country'] ?? null,
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'sub_city' => $data['sub_city'] ?? null,
            'woreda' => $data['woreda'] ?? null,
            'house_no' => $data['house_no'] ?? null,
        ], fn ($value) => $value !== null);

        $parent = ParentProfile::firstOrCreate(['user_id' => $user->id], $profileFields);

        if (! $parent->wasRecentlyCreated && $profileFields !== []) {
            // Enrich an existing profile without clobbering filled fields.
            $parent->fill(array_diff_key(
                $profileFields,
                array_filter($parent->only(array_keys($profileFields)), fn ($v) => $v !== null),
            ))->save();
        }

        return [$parent, $isNewAccount];
    }
}
