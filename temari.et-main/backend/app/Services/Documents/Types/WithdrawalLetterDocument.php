<?php

namespace App\Services\Documents\Types;

use App\Http\Controllers\Api\V1\StudentEnrollmentController;
use App\Models\GeneratedDocument;
use App\Models\StudentWithdrawal;
use App\Models\User;
use App\Services\Documents\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** The mid-year withdrawal / clearance letter. */
class WithdrawalLetterDocument extends DocumentType
{
    public function view(): string
    {
        return 'withdrawal-letter';
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return StudentWithdrawal::find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        return $subject instanceof StudentWithdrawal
            && $user->hasPermissionForScope('transfers.manage', $subject->school_id, $subject->branch_id);
    }

    public function anchor(?Model $subject, array $params): array
    {
        /** @var StudentWithdrawal $subject */
        return ['school_id' => $subject->school_id, 'branch_id' => $subject->branch_id];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var StudentWithdrawal $subject */
        // The QR token is lazily provisioned (same as the letter endpoint).
        if ($subject->public_token === null) {
            $subject->forceFill(['public_token' => Str::random(48)])->save();
        }

        return ['letter' => StudentEnrollmentController::withdrawalLetterPayload($subject)];
    }

    public function publiclyDownloadable(): bool
    {
        return true;
    }

    /** Same QR target as the HTML article — the public letter page. */
    public function qrTarget(GeneratedDocument $document): string
    {
        $withdrawal = $document->subject;

        if ($withdrawal instanceof StudentWithdrawal && $withdrawal->public_token) {
            return rtrim((string) config('sms.frontend_url'), '/').'/letters/withdrawal/'.$withdrawal->public_token;
        }

        return parent::qrTarget($document);
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $withdrawal = $document->subject;

        if (! $withdrawal instanceof StudentWithdrawal) {
            return [];
        }

        $withdrawal->load(['student:id,first_name,father_name,grandfather_name,public_id', 'school:id,name']);

        return [
            'reference' => sprintf('WD-%05d', $withdrawal->id),
            'student' => $withdrawal->student?->full_name,
            'school' => $withdrawal->school?->name,
            'issued_on' => $withdrawal->withdrawn_on?->toDateString(),
        ];
    }
}
