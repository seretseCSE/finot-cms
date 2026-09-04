<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;

/**
 * Resolves who hears about an invoice, on which channels — shared by the
 * on-demand notices (FeeNotifier) and the automated reminder ladder
 * (FeeReminderService) so both walk the exact same recipient set. Guardians
 * are gated by their channel preferences (notify_via_sms + the guardian
 * link's can_receive_sms, notify_via_email); students by their own user's
 * preferences.
 */
class InvoiceRecipients
{
    /**
     * Every deliverable (user, audience, channels) for one invoice — dedup'd
     * per user so someone who is both the student and their own guardian
     * never hears twice. Eager-load `student.user` and
     * `student.guardians.parentProfile.user` on the invoice first.
     *
     * @return list<array{user: User, audience: 'parents'|'students', sms: bool, email: bool}>
     */
    public static function for(Invoice $invoice, bool $parents, bool $students): array
    {
        $student = $invoice->student;
        if ($student === null) {
            return [];
        }

        $recipients = [];
        $seen = [];

        if ($students && $student->user !== null) {
            $recipients[] = self::deliverable($student->user, 'students', smsAllowed: true);
            $seen[$student->user->id] = true;
        }

        if ($parents) {
            foreach ($student->guardians as $link) {
                $user = $link->parentProfile?->user;
                if (! $link->is_active || $user === null || isset($seen[$user->id])) {
                    continue;
                }
                $seen[$user->id] = true;
                $recipients[] = self::deliverable($user, 'parents', smsAllowed: (bool) $link->can_receive_sms);
            }
        }

        // Drop recipients with no reachable channel at all.
        return array_values(array_filter($recipients, fn (array $r): bool => $r['sms'] || $r['email']));
    }

    /**
     * @return array{user: User, audience: 'parents'|'students', sms: bool, email: bool}
     */
    private static function deliverable(User $user, string $audience, bool $smsAllowed): array
    {
        return [
            'user' => $user,
            'audience' => $audience,
            'sms' => $smsAllowed && $user->phone !== null && $user->notify_via_sms,
            'email' => $user->email !== null && $user->notify_via_email,
        ];
    }
}
