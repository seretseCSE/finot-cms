<?php

namespace App\Services;

use App\Mail\TransferUpdateMail;
use App\Models\Student;
use App\Models\StudentTransferRequest;
use App\Models\StudentWithdrawal;
use App\Models\TransferApplication;
use App\Models\User;
use App\Services\Notify\Notifier;
use Illuminate\Support\Facades\Lang;

/**
 * Family comms for every student-movement event, routed through the
 * notification pipeline (ADR-018): in-app feed row always, SMS behind the
 * platform whitelist × notify_via_sms × the guardian link's can_receive_sms,
 * bespoke TransferUpdateMail behind notify_via_email. The "requested" alert
 * doubles as a safety signal: the family learns immediately if anyone tries
 * to move their child.
 */
class TransferNotifier
{
    public function __construct(private readonly Notifier $notifier)
    {
    }

    public function requested(StudentTransferRequest $transfer): void
    {
        $this->notifyFamily($transfer->student, 'movement.transfer_requested', 'transfers.requested_sms', self::transferVars($transfer));
    }

    public function approved(StudentTransferRequest $transfer): void
    {
        $this->notifyFamily($transfer->student, 'movement.transfer_approved', 'transfers.approved_sms', self::transferVars($transfer));
    }

    public function rejected(StudentTransferRequest $transfer): void
    {
        $this->notifyFamily($transfer->student, 'movement.transfer_rejected', 'transfers.rejected_sms', self::transferVars($transfer));
    }

    public function cancelled(StudentTransferRequest $transfer): void
    {
        $this->notifyFamily($transfer->student, 'movement.transfer_cancelled', 'transfers.cancelled_sms', self::transferVars($transfer));
    }

    public function withdrawn(StudentWithdrawal $withdrawal): void
    {
        $this->notifyFamily($withdrawal->student, 'movement.withdrawal', 'transfers.withdrawal_sms', [
            'from' => $withdrawal->school?->name ?? '',
        ]);
    }

    public function applicationAccepted(TransferApplication $application): void
    {
        $this->notifyFamily($application->student, 'movement.application_decided', 'transfers.application_accepted_sms', [
            ...self::applicationVars($application),
            'status' => 'accepted',
        ]);
    }

    public function applicationDeclined(TransferApplication $application): void
    {
        $this->notifyFamily($application->student, 'movement.application_decided', 'transfers.application_declined_sms', [
            ...self::applicationVars($application),
            'status' => 'declined',
        ]);
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function notifyFamily(?Student $student, string $event, string $smsKey, array $vars): void
    {
        $this->notifier->toFamily($student, $event, $vars, [
            'link' => '/me/transfers',
            'smsKey' => $smsKey,
            'mail' => fn (User $user, string $locale): TransferUpdateMail => new TransferUpdateMail(
                studentName: $student->full_name,
                message: Lang::get($smsKey, [...$vars, 'student' => $student->full_name], $locale),
                language: $locale,
            ),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function transferVars(StudentTransferRequest $transfer): array
    {
        $transfer->loadMissing(['fromSchool:id,name', 'toSchool:id,name']);

        return [
            'from' => $transfer->fromSchool?->name ?? '',
            'to' => $transfer->toSchool?->name ?? '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function applicationVars(TransferApplication $application): array
    {
        $application->loadMissing(['fromSchool:id,name', 'toSchool:id,name']);

        return [
            'from' => $application->fromSchool?->name ?? '',
            'to' => $application->toSchool?->name ?? '',
        ];
    }
}
