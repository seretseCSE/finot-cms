<?php

namespace App\Actions;

use App\Models\Student;
use App\Models\User;
use App\Services\ProfilePhotoSync;
use App\Services\RegistrationNotifier;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Provisions (or reuses) the student's OWN login and links it to the student
 * record. Registration creates one by default (every student gets credentials
 * from day one); a lane may still opt out explicitly. A family phone often
 * belongs to a guardian's account already — that is rejected here.
 *
 * Two modes:
 *  - PHONE: the student has their own number — account keyed by it, setup SMS
 *    to the student;
 *  - ID LOGIN (phone = null): a phone-less account that signs in with the
 *    student's Temari ID + PIN; the setup link + instructions are texted to
 *    the primary guardian instead.
 */
class LinkStudentLoginAction
{
    public function __construct(private readonly RegistrationNotifier $notifier) {}

    public function execute(Student $student, ?string $phone, ?string $email = null): User
    {
        return DB::transaction(function () use ($student, $phone, $email): User {
            if ($student->user_id !== null) {
                throw ValidationException::withMessages([
                    'phone' => ['This student already has a login account.'],
                ]);
            }

            if ($phone === null || trim($phone) === '') {
                return $this->provisionIdLogin($student);
            }

            $phone = PhoneNumber::normalize($phone) ?? trim($phone);

            $user = User::withTrashed()->firstOrNew(['phone' => $phone]);
            $isNewAccount = ! $user->exists || $user->password === null;

            if (! $user->exists) {
                if ($email !== null && User::withTrashed()->where('email', $email)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => ['This email already belongs to another Temari.et account.'],
                    ]);
                }

                $user->fill([
                    'name' => $student->full_name,
                    'email' => $email,
                    'preferred_language' => 'en',
                ])->save();
            } elseif ($user->parentProfile()->exists()) {
                // A guardian's own account can never double as a child's
                // login — the parent reaches everything through /me already.
                throw ValidationException::withMessages([
                    'phone' => ['This phone number belongs to a guardian\'s account. The student needs their own number — parents already see their children through their own login.'],
                ]);
            }

            if (Student::where('user_id', $user->id)->whereKeyNot($student->id)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => ['This phone number already belongs to another student\'s account.'],
                ]);
            }

            $student->forceFill(['user_id' => $user->id])->save();

            // A photo already on the record becomes the account's first avatar.
            ProfilePhotoSync::seed($student);

            $this->notifier->studentLinked($student, $user, $isNewAccount);

            return $user;
        });
    }

    /**
     * The phone-less lane: a fresh account with no phone of its own, reachable
     * only via student-ID + PIN. Nothing to dedupe on — every ID login is a
     * brand-new user. The guardian-facing setup SMS is the notifier's job.
     */
    private function provisionIdLogin(Student $student): User
    {
        $user = new User;
        $user->fill([
            'name' => $student->full_name,
            'preferred_language' => 'en',
        ])->save();

        $student->forceFill(['user_id' => $user->id])->save();

        ProfilePhotoSync::seed($student);

        $this->notifier->studentIdLoginProvisioned($student, $user);

        return $user;
    }
}
