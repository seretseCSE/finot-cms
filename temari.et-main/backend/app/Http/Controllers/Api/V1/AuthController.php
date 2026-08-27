<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\SetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\PasswordResetToken;
use App\Models\PasswordSetupToken;
use App\Services\Analytics\Analytics;
use App\Services\Notify\DeviceTracker;
use App\Services\Notify\Notifier;
use App\Services\Sms\SmsClient;
use App\Support\LoginIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const int MAX_LOGIN_ATTEMPTS = 5;

    private const int LOGIN_DECAY_SECONDS = 60;

    public function __construct(
        private readonly SmsClient $sms,
        private readonly DeviceTracker $devices,
        private readonly Notifier $notifier,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $identifier = $request->string('identifier')->toString();
        $throttleKey = self::throttleKey('login', $identifier, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'identifier' => [Lang::get('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)])],
            ]);
        }

        $user = LoginIdentifier::resolve($identifier);

        if ($user === null || $user->password === null || ! Hash::check($request->string('password'), $user->password)) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            // One generic message for every failure shape — a student ID must
            // not be confirmable by probing which error comes back.
            throw ValidationException::withMessages([
                'identifier' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'identifier' => ['Your account has been deactivated. Please contact support for assistance.'],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $user->forceFill(['last_login_at' => now()])->save();
        $this->devices->track($user, $request);

        Analytics::identify($user);
        Analytics::capture($user, 'user.logged_in', [
            'via' => LoginIdentifier::isStudentId($identifier) ? 'student_id' : 'phone',
        ]);

        $token = $user->createToken('api')->plainTextToken;
        $user->load('memberships');

        return (new UserResource($user))
            ->additional(['meta' => ['token' => $token], 'message' => 'Logged in successfully.'])
            ->response();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('memberships'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * PIN-reset OTP request by phone OR student ID. The OTP is delivered to
     * the account's own phone, or — for phone-less ID-login students — to the
     * primary guardian's phone. The response never reveals whether the
     * identifier exists or where anything was sent.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $generic = response()->json(['message' => 'If this account exists, a reset code has been sent by SMS.']);

        $identifier = $request->string('identifier')->toString();
        $throttleKey = self::throttleKey('forgot', $identifier, $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return $generic;
        }

        RateLimiter::hit($throttleKey, 600);

        $user = LoginIdentifier::resolve($identifier);

        if ($user === null || ! $user->isActive()) {
            return $generic;
        }

        $delivery = LoginIdentifier::resetDelivery($user);

        if ($delivery === null) {
            return $generic;
        }

        // Invalidate any previous unused codes for this account.
        PasswordResetToken::where('user_id', $user->id)->whereNull('used_at')->delete();

        $otp = (string) random_int(100000, 999999);

        PasswordResetToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(10),
        ]);

        $message = $delivery['via_guardian']
            ? Lang::get('auth.reset_otp_guardian_sms', ['student' => $delivery['student_name'], 'code' => $otp], $delivery['locale'])
            : Lang::get('auth.reset_otp_sms', ['code' => $otp], $delivery['locale']);

        try {
            $this->sms->send($delivery['phone'], $message);
        } catch (\Throwable $e) {
            Log::warning('PIN reset OTP SMS failed.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        return $generic;
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $identifier = $request->string('identifier')->toString();
        $otp = $request->string('otp')->toString();

        $user = LoginIdentifier::resolve($identifier);

        $resetToken = $user === null ? null : PasswordResetToken::where('user_id', $user->id)
            ->where('token', hash('sha256', $otp))
            ->first();

        if ($resetToken === null || ! $resetToken->isValid()) {
            throw ValidationException::withMessages([
                'otp' => ['The OTP is invalid or has expired.'],
            ]);
        }

        $user->password = Hash::make($request->string('password'));
        // This response carries a session token — it IS a sign-in, so the
        // portal-account chip must move off "Never logged in".
        $user->last_login_at = now();
        $user->save();

        $resetToken->forceFill(['used_at' => now()])->save();

        // Someone who did NOT reset this password must learn about it fast.
        $this->notifier->toUser($user, 'security.password_changed', [], ['link' => '/settings']);
        $this->devices->track($user, $request);

        Analytics::capture($user, 'user.password_reset');

        $token = $user->createToken('api')->plainTextToken;
        $user->load('memberships');

        return (new UserResource($user))
            ->additional(['meta' => ['token' => $token], 'message' => 'Password reset successfully.'])
            ->response();
    }

    public function setPassword(SetPasswordRequest $request): JsonResponse
    {
        $setupToken = PasswordSetupToken::where('token', hash('sha256', $request->string('token')))->first();

        if ($setupToken === null || ! $setupToken->isValid()) {
            throw ValidationException::withMessages([
                'token' => ['This link is invalid or has expired.'],
            ]);
        }

        $user = $setupToken->user;
        $user->password = Hash::make($request->string('password'));
        // Same as resetPassword: setting the PIN from an invite link signs
        // the user straight in — stamp it so staff see "Active", not
        // "Never logged in".
        $user->last_login_at = now();
        $user->save();

        $setupToken->forceFill(['used_at' => now()])->save();

        // First activation — record the device silently (no "new device"
        // alert for the sign-up someone just performed).
        $this->devices->track($user, $request);

        $token = $user->createToken('api')->plainTextToken;
        $user->load('memberships');

        return (new UserResource($user))
            ->additional(['meta' => ['token' => $token], 'message' => 'Password set successfully.'])
            ->response();
    }

    private static function throttleKey(string $bucket, string $identifier, ?string $ip): string
    {
        return $bucket.':'.sha1(mb_strtolower($identifier).'|'.($ip ?? ''));
    }
}
