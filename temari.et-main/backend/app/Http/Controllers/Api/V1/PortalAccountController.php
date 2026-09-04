<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\LinkStudentLoginAction;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Controller;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;
use App\Rules\EthiopianPhone;
use App\Services\PasswordSetupService;
use App\Support\ActivityLogger;
use App\Support\LoginIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

/**
 * Staff lane for family portal logins. Students may get their OWN account
 * provisioned post-registration (explicit phone — never guessed); both
 * students and parents with a provisioned-but-unused account can be re-sent
 * their password-setup SMS. Live custody required throughout (ADR-017).
 */
class PortalAccountController extends Controller
{
    use HandlesBulkActions;

    public function __construct(private readonly PasswordSetupService $passwordSetup)
    {
    }

    /**
     * Create + link the student's own login. With a phone the setup link is
     * texted to the student; without one an ID-login account (student ID +
     * PIN) is provisioned and the setup SMS goes to the primary guardian.
     */
    public function storeForStudent(
        Request $request,
        Student $student,
        LinkStudentLoginAction $linkLogin,
    ): JsonResponse {
        $this->authorize('update', $student);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:20', new EthiopianPhone()],
        ]);

        $linkLogin->execute($student, $data['phone'] ?? null);

        ActivityLogger::log($request->user(), 'student.portal_account_created', $student);
        $student->load('user');

        return response()->json([
            'data' => $this->accountPayload($student->user),
            'message' => ($data['phone'] ?? null) !== null
                ? 'Login account created. A setup link has been sent by SMS.'
                : 'ID login created. Setup instructions were sent to the primary guardian by SMS.',
        ]);
    }

    /** Re-send the password-setup SMS for a student's unused login. */
    public function inviteStudent(Request $request, Student $student): JsonResponse
    {
        $this->authorize('update', $student);

        $user = $student->user;
        if ($user === null) {
            throw ValidationException::withMessages([
                'phone' => ['This student has no login account yet.'],
            ]);
        }

        $this->assertInvitable($user);

        // Phone-less ID-login accounts deliver to the primary guardian.
        $delivery = LoginIdentifier::resetDelivery($user);

        if ($delivery === null) {
            throw ValidationException::withMessages([
                'phone' => ['No reachable phone: the student has no number and no SMS-consented guardian.'],
            ]);
        }

        $message = $delivery['via_guardian']
            ? Lang::get('registration.student_id_setup_sms', [
                'student' => $student->full_name,
                'school' => $student->branch?->school?->name ?? 'your school',
                'id' => $student->public_id,
            ], $delivery['locale'])
            : null;

        $this->passwordSetup->sendLink($user, $message, $delivery['phone']);
        ActivityLogger::log($request->user(), 'student.portal_invite_sent', $student);

        return response()->json(['message' => $delivery['via_guardian']
            ? 'Setup link sent to the primary guardian by SMS.'
            : 'Setup link sent by SMS.']);
    }

    /** Re-send the password-setup SMS for a guardian's unused login. */
    public function inviteParent(Request $request, ParentProfile $parent): JsonResponse
    {
        $allowed = collect($parent->activeAdminScopes())->contains(
            fn (array $scope) => $request->user()->hasPermissionForScope('guardians.manage', $scope[0], $scope[1]),
        );
        abort_unless($allowed || $request->user()->isSuperAdmin(), 403);

        $user = $parent->user;
        if ($user === null) {
            throw ValidationException::withMessages([
                'phone' => ['This guardian has no login account.'],
            ]);
        }

        $this->assertInvitable($user);
        $this->passwordSetup->sendLink($user);
        ActivityLogger::log($request->user(), 'parent.portal_invite_sent', $parent);

        return response()->json(['message' => 'Setup link sent by SMS.']);
    }

    /**
     * Re-send the setup SMS to a whole selection of guardians — onboarding a
     * grade's families in one pass instead of one row at a time. Each guardian
     * is scope-checked on their own; accounts with no login, already in use, or
     * unreachable are skipped and reported.
     */
    public function bulkInviteParents(Request $request): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $sent = 0;
        $skipped = [];

        $rows = $this->bulkRows($data['ids'], ParentProfile::with('user'), $skipped);

        foreach ($rows as $parent) {
            $name = $parent->user?->name ?? $parent->first_name;

            $allowed = $actor->isSuperAdmin() || collect($parent->activeAdminScopes())->contains(
                fn (array $scope) => $actor->hasPermissionForScope('guardians.manage', $scope[0], $scope[1]),
            );

            if (! $allowed) {
                $skipped[] = self::skipRow($parent, $name, 'not_permitted');

                continue;
            }

            $reason = $this->inviteDenial($parent->user);

            if ($reason !== null) {
                $skipped[] = self::skipRow($parent, $name, $reason);

                continue;
            }

            $this->passwordSetup->sendLink($parent->user);
            ActivityLogger::log($actor, 'parent.portal_invite_sent', $parent);
            $sent++;
        }

        return response()->json([
            'message' => "Setup link sent to {$sent} guardian(s).",
            'meta' => ['sent' => $sent, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * The same sweep for students. Phone-less ID-login accounts route to the
     * primary guardian exactly as the single-row invite does.
     */
    public function bulkInviteStudents(Request $request): JsonResponse
    {
        $data = $request->validate(self::bulkIdRules());

        $actor = $request->user();
        $sent = 0;
        $skipped = [];

        $rows = $this->bulkRows($data['ids'], Student::with(['user', 'branch.school']), $skipped);

        foreach ($rows as $student) {
            $name = $student->full_name;

            if ($actor->cannot('update', $student)) {
                $skipped[] = self::skipRow($student, $name, 'not_permitted');

                continue;
            }

            $reason = $this->inviteDenial($student->user);

            if ($reason !== null) {
                $skipped[] = self::skipRow($student, $name, $reason);

                continue;
            }

            $delivery = LoginIdentifier::resetDelivery($student->user);

            if ($delivery === null) {
                $skipped[] = self::skipRow($student, $name, 'not_reachable');

                continue;
            }

            $message = $delivery['via_guardian']
                ? Lang::get('registration.student_id_setup_sms', [
                    'student' => $student->full_name,
                    'school' => $student->branch?->school?->name ?? 'your school',
                    'id' => $student->public_id,
                ], $delivery['locale'])
                : null;

            $this->passwordSetup->sendLink($student->user, $message, $delivery['phone']);
            ActivityLogger::log($actor, 'student.portal_invite_sent', $student);
            $sent++;
        }

        return response()->json([
            'message' => "Setup link sent for {$sent} student(s).",
            'meta' => ['sent' => $sent, 'requested' => count($data['ids']), 'skipped' => $skipped],
        ]);
    }

    /**
     * Invites are for accounts that were never used — an account that already
     * signed in resets its own PIN through the forgot-PIN lane.
     */
    private function assertInvitable(User $user): void
    {
        if ($user->password !== null && $user->last_login_at !== null) {
            throw ValidationException::withMessages([
                'phone' => ['This account is already in use. They can reset their PIN from the login page.'],
            ]);
        }
    }

    /** The bulk-safe form of assertInvitable: a skip reason, or null when fine. */
    private function inviteDenial(?User $user): ?string
    {
        if ($user === null) {
            return 'no_account';
        }

        return $user->password !== null && $user->last_login_at !== null
            ? 'account_in_use'
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(User $user): array
    {
        return [
            'status' => $user->status->value,
            'status_label' => $user->status->label(),
            'has_password' => $user->password !== null,
            'last_login_at' => $user->last_login_at,
            'phone' => $user->phone,
            // Phone-less accounts sign in with the student's Temari ID + PIN.
            'login_mode' => $user->phone !== null ? 'phone' : 'student_id',
        ];
    }
}
