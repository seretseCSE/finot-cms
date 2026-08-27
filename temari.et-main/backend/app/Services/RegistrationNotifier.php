<?php

namespace App\Services;

use App\Mail\ChildRegisteredMail;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use App\Services\Notify\Notifier;
use App\Services\Sms\SmsClient;
use App\Support\CommsMute;
use App\Support\LoginIdentifier;
use App\Support\NotificationCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Registration comms, localized per recipient (users.preferred_language) and
 * gated by their channel preferences. Everything is deferred with
 * DB::afterCommit so a rolled-back registration never texts anyone. SMS
 * bodies live in lang/{en,am,om}/registration.php. Failures are logged, never
 * thrown — comms must not undo a registration.
 */
class RegistrationNotifier
{
    public function __construct(
        private readonly SmsClient $sms,
        private readonly PasswordSetupService $passwordSetup,
        private readonly Notifier $notifier,
    ) {}

    /**
     * A guardian was linked to a student. New account → password-setup SMS
     * with registration context; existing account → contextual notice
     * (respecting notify_via_sms + the link's can_receive_sms). Email goes out
     * in parallel when the guardian has one and notify_via_email is on.
     */
    public function guardianLinked(StudentGuardian $guardian, Student $student, bool $isNewAccount): void
    {
        DB::afterCommit(function () use ($guardian, $student, $isNewAccount): void {
            $user = $guardian->parentProfile?->user;

            if ($user === null) {
                return;
            }

            $vars = [
                'student' => $student->full_name,
                'school' => self::schoolName($student),
            ];
            $locale = $user->preferred_language ?: 'en';

            if (! $isNewAccount) {
                $this->notifier->inApp($user, 'family.child_registered', $vars, [
                    'link' => '/me/children',
                ]);
            }

            // Bulk import with sending off: the account exists but stays
            // quiet — staff bulk-invite from the portal-accounts lane once
            // the imported data is verified.
            if (CommsMute::active()) {
                return;
            }

            try {
                if ($isNewAccount) {
                    // Setup links bypass notify_via_sms — without one the new
                    // account is unreachable.
                    $this->passwordSetup->sendLink(
                        $user,
                        Lang::get('registration.guardian_setup_sms', $vars, $locale),
                    );
                } elseif ($user->notify_via_sms && $guardian->can_receive_sms
                    && NotificationCatalog::smsAllowed('family.child_registered')) {
                    $this->sms->send(
                        $user->phone,
                        Lang::get('registration.guardian_registered_sms', $vars, $locale),
                    );
                }

                if ($user->email !== null && $user->notify_via_email) {
                    Mail::to($user->email)->send(new ChildRegisteredMail(
                        studentName: $student->full_name,
                        schoolName: $vars['school'],
                        relationshipLabel: $guardian->relationship->label(),
                        language: $locale,
                    ));
                }
            } catch (\Throwable $e) {
                Log::warning('Guardian registration notification failed.', [
                    'user_id' => $user->id,
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * A student's own user account was linked at registration. New account →
     * setup link; existing → contextual notice.
     */
    public function studentLinked(Student $student, User $user, bool $isNewAccount): void
    {
        DB::afterCommit(function () use ($student, $user, $isNewAccount): void {
            $vars = ['school' => self::schoolName($student)];
            $locale = $user->preferred_language ?: 'en';

            if (! $isNewAccount) {
                $this->notifier->inApp($user, 'family.child_registered', [
                    ...$vars,
                    'student' => $student->full_name,
                ], ['link' => '/me/student']);
            }

            if (CommsMute::active()) {
                return;
            }

            try {
                if ($isNewAccount) {
                    $this->passwordSetup->sendLink(
                        $user,
                        Lang::get('registration.student_setup_sms', $vars, $locale),
                    );
                } elseif ($user->notify_via_sms
                    && NotificationCatalog::smsAllowed('family.child_registered')) {
                    $this->sms->send(
                        $user->phone,
                        Lang::get('registration.student_registered_sms', $vars, $locale),
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Student registration notification failed.', [
                    'user_id' => $user->id,
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * A phone-less ID-login account was provisioned for the student. The
     * setup link is texted to the PRIMARY guardian (best SMS-consented link),
     * in the guardian's language, naming the child and the Temari student ID
     * they will sign in with — the message must never read like it concerns
     * the guardian's own parent account.
     */
    public function studentIdLoginProvisioned(Student $student, User $user): void
    {
        DB::afterCommit(function () use ($student, $user): void {
            if (CommsMute::active()) {
                return;
            }

            $delivery = LoginIdentifier::resetDelivery($user);

            try {
                if ($delivery === null) {
                    // No reachable guardian phone: keep the account, skip the
                    // SMS — staff can re-send the setup link from the
                    // student's portal-account section later.
                    Log::info('ID-login setup SMS skipped: no guardian phone.', [
                        'student_id' => $student->id,
                        'user_id' => $user->id,
                    ]);

                    return;
                }

                $this->passwordSetup->sendLink(
                    $user,
                    Lang::get('registration.student_id_setup_sms', [
                        'student' => $student->full_name,
                        'school' => self::schoolName($student),
                        'id' => $student->public_id,
                    ], $delivery['locale']),
                    $delivery['phone'],
                );
            } catch (\Throwable $e) {
                Log::warning('Student ID-login notification failed.', [
                    'user_id' => $user->id,
                    'student_id' => $student->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private static function schoolName(Student $student): string
    {
        return $student->branch?->school?->name
            ?? $student->currentEnrollment?->branch?->school?->name
            ?? 'your school';
    }
}
