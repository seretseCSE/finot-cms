<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AccountLinkRequest;
use App\Models\SignupOtp;
use App\Models\Student;
use App\Models\User;
use App\Rules\EthiopianPhone;
use App\Services\Analytics\Analytics;
use App\Services\Notify\Notifier;
use App\Services\Sms\SmsClient;
use App\Support\PhoneNumber;
use App\Support\PublicId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Public self-signup: phone → SMS OTP → PIN. The OTP-verified phone is the
 * identity anchor (Ethiopia is phone-first — no email, no access codes):
 *
 *  - phone matches a PROVISIONED account (guardian/student the school
 *    registered, password never set) → signup becomes ACTIVATION; their
 *    school lanes appear automatically through the existing links.
 *  - unknown phone → a public B2C account (exam prep / mock lane, no school).
 *  - optional Temari student ID → auto-link only when the phone matches the
 *    student record; otherwise a PENDING claim the registrar approves. Never
 *    an instant link by ID alone.
 */
class SignupController extends Controller
{
    public function __construct(private readonly SmsClient $sms) {}

    public function requestOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', new EthiopianPhone],
            'preferred_language' => ['nullable', 'in:en,am,om'],
        ]);

        $phone = PhoneNumber::normalize($data['phone']);
        $existing = User::withTrashed()->where('phone', $phone)->first();

        if ($existing !== null && $existing->password !== null) {
            // Machine-readable so the signup screen can offer an inline
            // sign-in (the phone is already typed) instead of a dead end.
            return response()->json([
                'message' => 'An account with this phone number already exists. Log in instead, or reset your PIN.',
                'code' => 'account_exists',
            ], 409);
        }

        SignupOtp::where('phone', $phone)->whereNull('used_at')->delete();

        $otp = (string) random_int(100000, 999999);

        SignupOtp::create([
            'phone' => $phone,
            'token' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->sms->send($phone, Lang::get(
            'auth.signup_otp_sms',
            ['code' => $otp],
            $data['preferred_language'] ?? 'en',
        ));

        return response()->json(['message' => 'A verification code has been sent by SMS.']);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20', new EthiopianPhone],
            'otp' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(4)],
            'name' => ['nullable', 'string', 'max:255'],
            'preferred_language' => ['nullable', 'in:en,am,om'],
            'student_public_id' => ['nullable', 'string', 'max:12'],
        ]);

        $phone = PhoneNumber::normalize($data['phone']);

        $otp = SignupOtp::where('phone', $phone)
            ->where('token', hash('sha256', $data['otp']))
            ->first();

        if ($otp === null || ! $otp->isValid()) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code is invalid or has expired.'],
            ]);
        }

        [$user, $linked] = DB::transaction(function () use ($data, $phone, $otp) {
            $user = User::withTrashed()->firstOrNew(['phone' => $phone]);

            if ($user->exists && $user->password !== null) {
                throw ValidationException::withMessages([
                    'phone' => ['An account with this phone number already exists. Log in instead.'],
                ]);
            }

            if ($user->exists && $user->trashed()) {
                throw ValidationException::withMessages([
                    'phone' => ['This account has been deactivated. Please contact support.'],
                ]);
            }

            $isActivation = $user->exists;

            if (! $user->exists) {
                if (blank($data['name'] ?? null)) {
                    throw ValidationException::withMessages([
                        'name' => ['Your name is required to create an account.'],
                    ]);
                }

                $user->fill(['name' => trim($data['name'])]);
            }

            $user->preferred_language = $data['preferred_language'] ?? $user->preferred_language ?? 'en';
            $user->password = Hash::make($data['password']);
            $user->save();
            // A brand-new row only carries DB defaults (status) after a
            // round-trip — refresh before the isActive gate below reads it.
            $user->refresh();

            $otp->forceFill(['used_at' => now()])->save();

            $linked = $this->detectLinks($user, $isActivation);

            if (filled($data['student_public_id'] ?? null)) {
                $linked = $this->handleStudentId($user, $data['student_public_id']) ?? $linked;
            }

            return [$user, $linked];
        });

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'phone' => ['This account has been deactivated. Please contact support.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('api')->plainTextToken;
        $user->load('memberships');

        Analytics::identify($user, ['signed_up_at' => now()->toIso8601String()]);
        Analytics::capture($user, 'user.signed_up', ['linked' => $linked]);

        return (new UserResource($user))
            ->additional(['meta' => ['token' => $token, 'linked' => $linked], 'message' => 'Account ready.'])
            ->response();
    }

    /**
     * What the verified phone already connects this person to — purely
     * informational for the welcome screen; the /me lane derives real access
     * from the underlying links.
     */
    private function detectLinks(User $user, bool $isActivation): string
    {
        if (! $isActivation) {
            return 'none';
        }

        if ($user->parentProfile()->whereHas('guardianships')->exists()) {
            return 'parent';
        }

        if (Student::where('user_id', $user->id)->exists()) {
            return 'student';
        }

        return 'none';
    }

    /**
     * Resolve an optional Temari student ID: auto-link only on a phone match
     * with the student record; otherwise file a pending registrar claim.
     */
    private function handleStudentId(User $user, string $rawId): ?string
    {
        $student = Student::where('public_id', PublicId::normalize($rawId))->first();

        if ($student === null) {
            throw ValidationException::withMessages([
                'student_public_id' => ['We could not find that Temari.et ID. Check it with your school, or leave it empty for now.'],
            ]);
        }

        if ($student->user_id === $user->id) {
            return 'student';
        }

        if ($student->user_id !== null) {
            throw ValidationException::withMessages([
                'student_public_id' => ['This student already has a login account. Please contact the school.'],
            ]);
        }

        if ($student->primary_phone !== null && PhoneNumber::normalize($student->primary_phone) === $user->phone) {
            $student->forceFill(['user_id' => $user->id])->save();

            return 'student';
        }

        $claim = AccountLinkRequest::firstOrCreate(
            ['user_id' => $user->id, 'student_id' => $student->id, 'status' => AccountLinkRequest::STATUS_PENDING],
        );

        // The registrar at the student's live scope reviews every claim.
        if ($claim->wasRecentlyCreated) {
            $branch = $student->currentEnrollment?->branch ?? $student->branch;

            if ($branch !== null) {
                app(Notifier::class)->toStaff(
                    $branch->school_id,
                    $branch->id,
                    'students.manage',
                    'family.account_link_requested',
                    ['student' => $student->full_name],
                    ['link' => '/users?tab=claims'],
                );
            }
        }

        return 'claim_pending';
    }
}
