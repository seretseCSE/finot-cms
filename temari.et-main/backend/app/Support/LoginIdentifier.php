<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;

/**
 * Resolves the single login "identifier" field: an Ethiopian phone number
 * (every account type) or a Temari student ID (H8R6WV — the ID-login lane for
 * students without their own phone, ADR-012).
 *
 * The student-ID path is deliberately narrow: it only ever resolves through
 * `students.public_id → students.user_id`, and never to an account that holds
 * memberships (staff/platform authority) or a parent profile — a semi-public
 * code printed on an ID card must not become a staff or guardian login
 * handle. Callers keep errors GENERIC so identifiers can't be enumerated.
 */
final class LoginIdentifier
{
    public static function isPhone(string $raw): bool
    {
        return PhoneNumber::normalize($raw) !== null;
    }

    public static function isStudentId(string $raw): bool
    {
        $id = PublicId::normalize($raw);

        return strlen($id) === PublicId::LENGTH
            && preg_match('/^['.PublicId::ALPHABET.']+$/', $id) === 1;
    }

    public static function resolve(string $raw): ?User
    {
        if (self::isPhone($raw)) {
            return User::findByPhone($raw);
        }

        if (! self::isStudentId($raw)) {
            return null;
        }

        $user = Student::where('public_id', PublicId::normalize($raw))->first()?->user;

        if ($user === null
            || $user->memberships()->exists()
            || $user->parentProfile()->exists()) {
            return null;
        }

        return $user;
    }

    /**
     * Where a PIN-reset OTP for this account should be delivered: the user's
     * own phone when they have one, else the best guardian phone of the
     * linked student (primary first, then priority order, SMS-consented links
     * only). Null = no reachable phone; the caller answers generically.
     *
     * The returned locale is the RECIPIENT's preferred language — the SMS must
     * read naturally to whoever's phone buzzes.
     *
     * @return array{phone: string, via_guardian: bool, student_name: ?string, locale: string}|null
     */
    public static function resetDelivery(User $user): ?array
    {
        if ($user->phone !== null) {
            return [
                'phone' => $user->phone,
                'via_guardian' => false,
                'student_name' => null,
                'locale' => $user->preferred_language ?: 'en',
            ];
        }

        $student = Student::where('user_id', $user->id)->first();

        if ($student === null) {
            return null;
        }

        $guardianUser = $student->guardians()
            ->where('is_active', true)
            ->where('can_receive_sms', true)
            ->orderByDesc('is_primary')
            ->orderBy('priority_order')
            ->with('parentProfile.user:id,phone,preferred_language')
            ->get()
            ->map(fn ($guardian) => $guardian->parentProfile?->user)
            ->first(fn (?User $u) => $u?->phone !== null);

        if ($guardianUser === null) {
            return null;
        }

        return [
            'phone' => $guardianUser->phone,
            'via_guardian' => true,
            'student_name' => $student->full_name,
            'locale' => $guardianUser->preferred_language ?: 'en',
        ];
    }
}
