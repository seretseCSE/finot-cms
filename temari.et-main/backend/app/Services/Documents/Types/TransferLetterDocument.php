<?php

namespace App\Services\Documents\Types;

use App\Enums\TransferRequestStatus;
use App\Http\Controllers\Api\V1\TransferController;
use App\Models\GeneratedDocument;
use App\Models\StudentTransferRequest;
use App\Models\User;
use App\Services\Documents\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** The official transfer letter — approved transfers only, either side may print. */
class TransferLetterDocument extends DocumentType
{
    public function view(): string
    {
        return 'transfer-letter';
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return StudentTransferRequest::find($subjectId);
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        return $subject instanceof StudentTransferRequest
            && ($user->hasPermissionForScope('transfers.manage', $subject->from_school_id, $subject->from_branch_id)
                || $user->hasPermissionForScope('transfers.manage', $subject->to_school_id, $subject->to_branch_id));
    }

    public function anchor(?Model $subject, array $params): array
    {
        /** @var StudentTransferRequest $subject */
        return ['school_id' => $subject->from_school_id, 'branch_id' => $subject->from_branch_id];
    }

    public function payload(?Model $subject, array $params): array
    {
        /** @var StudentTransferRequest $subject */
        if ($subject->status !== TransferRequestStatus::Approved) {
            throw ValidationException::withMessages([
                'document' => ['Only approved transfers have a letter.'],
            ]);
        }

        return ['letter' => TransferController::letterPayload($subject)];
    }

    public function publiclyDownloadable(): bool
    {
        return true;
    }

    /** Same QR target as the HTML article — the public letter page. */
    public function qrTarget(GeneratedDocument $document): string
    {
        $transfer = $document->subject;

        if ($transfer instanceof StudentTransferRequest && $transfer->public_token) {
            return rtrim((string) config('sms.frontend_url'), '/').'/letters/transfer/'.$transfer->public_token;
        }

        return parent::qrTarget($document);
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        $transfer = $document->subject;

        if (! $transfer instanceof StudentTransferRequest) {
            return [];
        }

        $transfer->load(['student:id,first_name,father_name,grandfather_name,public_id', 'fromSchool:id,name', 'toSchool:id,name']);

        return [
            'reference' => sprintf('TR-%05d', $transfer->id),
            'student' => $transfer->student?->full_name,
            'from_school' => $transfer->fromSchool?->name,
            'to_school' => $transfer->toSchool?->name,
            'issued_on' => $transfer->decided_at?->toDateString(),
        ];
    }
}
