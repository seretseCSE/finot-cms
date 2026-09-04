<?php

namespace App\Services\Documents\Types;

use App\Models\Branch;
use App\Models\GeneratedDocument;
use App\Models\School;
use App\Models\User;
use App\Services\Documents\DocumentType;
use App\Services\Reports\FinanceStatementService;
use Illuminate\Database\Eloquent\Model;

/**
 * The printable income–expense statement for one scope × window. No model
 * subject — the anchor lives in params (school_id, branch_id?, from, to).
 */
class FinanceStatementDocument extends DocumentType
{
    public function __construct(private readonly FinanceStatementService $statements)
    {
    }

    public function view(): string
    {
        return 'finance-statement';
    }

    public function rules(): array
    {
        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ];
    }

    public function resolveSubject(?int $subjectId): ?Model
    {
        return null;
    }

    public function authorize(User $user, ?Model $subject, array $params): bool
    {
        $schoolId = (int) ($params['school_id'] ?? 0);
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;

        if ($branchId !== null
            && Branch::query()->whereKey($branchId)->value('school_id') !== $schoolId) {
            return false;
        }

        return $user->hasPermissionForScope('finance.books.view', $schoolId, $branchId);
    }

    public function anchor(?Model $subject, array $params): array
    {
        return [
            'school_id' => (int) $params['school_id'],
            'branch_id' => isset($params['branch_id']) ? (int) $params['branch_id'] : null,
        ];
    }

    public function payload(?Model $subject, array $params): array
    {
        $schoolId = (int) $params['school_id'];
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;

        return [
            'statement' => $this->statements->build($schoolId, $branchId, $params['from'], $params['to']),
            'school_name' => School::query()->whereKey($schoolId)->value('name'),
            'branch_name' => $branchId ? Branch::query()->whereKey($branchId)->value('name') : null,
            'from' => $params['from'],
            'to' => $params['to'],
        ];
    }

    public function verifySummary(GeneratedDocument $document): array
    {
        return [
            'school' => $document->school?->name,
            'branch' => $document->branch?->name,
            'window' => ($document->params['from'] ?? '').' – '.($document->params['to'] ?? ''),
            'issued_on' => $document->created_at?->toDateString(),
        ];
    }
}
