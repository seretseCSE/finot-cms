<?php

namespace App\Services;

use App\Models\PasswordSetupToken;
use App\Models\User;
use App\Services\Sms\SmsClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Issues a single-use, hashed password-setup token for a freshly provisioned
 * account and texts the user a link to choose their password. Used for both
 * school staff and parents/guardians.
 */
class PasswordSetupService
{
    public function __construct(private readonly SmsClient $sms) {}

    /**
     * @param  string|null  $message  Custom SMS body; a `:link` placeholder is
     *                                replaced with the setup URL (appended when absent).
     * @param  string|null  $toPhone  Deliver to this number instead of the user's own —
     *                                the phone-less ID-login student case, where the
     *                                setup link goes to the primary guardian.
     */
    public function sendLink(User $user, ?string $message = null, ?string $toPhone = null): void
    {
        $plainToken = Str::random(48);

        PasswordSetupToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $link = rtrim((string) config('sms.frontend_url'), '/').'/set-password?token='.$plainToken;

        $body = $message === null
            ? "Welcome to Temari.et. Set your password to get started: {$link}"
            : (str_contains($message, ':link') ? str_replace(':link', $link, $message) : "{$message} {$link}");

        $recipient = $toPhone ?? $user->phone;

        if ($recipient === null) {
            return; // Token stays valid; the link can be re-sent once a phone exists.
        }

        $this->sms->send($recipient, $body);
    }
}
