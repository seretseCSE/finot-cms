<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Controller;
use App\Models\CardRequest;
use App\Models\Employee;
use App\Models\IdCard;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\Ethiopia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The MIFARE card register. ISSUANCE (individual, bulk, replacement, revoke)
 * is Temari.et platform territory — we print and deliver the physical cards.
 * School staff view their register and REPORT lost/damaged cards
 * (`cards.report`), which opens a card_requests fulfilment row.
 */
class IdCardController extends Controller
{
    use HandlesListQueries;

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasContextPermission('cards.view')
            || $request->user()->hasPlatformPermission('cards.manage'),
            403,
        );

        $branch = $this->activeBranchOrNull($request);
        $schoolScopeId = $this->activeSchoolScopeId($request);

        $query = IdCard::query()
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when(! $branch && $schoolScopeId, fn ($q) => $q->where('school_id', $schoolScopeId))
            ->when($request->filled('school_id'), fn ($q) => $q->where('school_id', $request->integer('school_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->with(['holder', 'issuedBy:id,name', 'school:id,name', 'branch:id,name']);

        if ($statuses = $this->csvValues($request, 'status')) {
            $query->whereIn('status', array_intersect($statuses, IdCard::STATUSES));
        }

        if ($types = $this->csvValues($request, 'holder_type')) {
            $map = ['student' => Student::class, 'employee' => Employee::class];
            $query->whereIn('holder_type', array_values(array_intersect_key($map, array_flip($types))));
        }

        $this->applySearch($query, $request, fn ($q, string $n) => $q
            ->where('card_uid', 'ilike', $this->needle($n))
            ->orWhereHasMorph(
                'holder',
                [Student::class, Employee::class],
                fn (Builder $h) => $h->where('search_text', 'ilike', $this->needle($n)),
            ));

        $this->applySort($query, $request, ['created_at', 'issued_on', 'status'], 'created_at');

        $cards = $query->paginate($this->perPage($request))->withQueryString();

        return response()->json([
            'data' => collect($cards->items())->map(fn (IdCard $c) => $this->row($c)),
            'meta' => [
                'current_page' => $cards->currentPage(),
                'last_page' => $cards->lastPage(),
                'total' => $cards->total(),
                'per_page' => $cards->perPage(),
            ],
        ]);
    }

    /**
     * People of a branch with NO active card — the bulk-issue worklist.
     * Students carry grade/section as their own fields (the studio filters by
     * them and shows a Class column) and come ALPHABETICALLY — the office
     * calls names off an A-to-Z list (Abdul, 2026-07-25).
     */
    public function candidates(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPlatformPermission('cards.manage'), 403);

        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'type' => ['required', Rule::in(['student', 'employee'])],
        ]);

        if ($data['type'] === 'employee') {
            $rows = Employee::query()
                ->where('branch_id', $data['branch_id'])
                ->where('is_active', true)
                ->whereNotExists($this->activeCardExists(Employee::class, 'employees.id'))
                ->with('positions')
                ->orderBy('first_name')
                ->limit(500)
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'detail' => $e->positions->whereNull('ended_on')->pluck('job_title')->implode(', '),
                ]);
        } else {
            $rows = StudentEnrollment::query()
                ->where('branch_id', $data['branch_id'])
                ->where('status', EnrollmentStatus::Active->value)
                ->whereHas('academicYear', fn ($q) => $q->where('status', 'active'))
                ->whereNotExists($this->activeCardExists(Student::class, 'student_enrollments.student_id'))
                ->with(['student', 'gradeLevel:id,name,sort_order', 'section:id,name'])
                ->get()
                ->sortBy(fn (StudentEnrollment $e) => mb_strtolower($e->student?->full_name ?? ''))
                ->values()
                ->map(fn (StudentEnrollment $e) => [
                    'id' => $e->student_id,
                    'name' => $e->student?->full_name,
                    'grade' => $e->gradeLevel?->name,
                    'grade_sort' => $e->gradeLevel?->sort_order,
                    'section' => $e->section?->name,
                    'detail' => trim(($e->gradeLevel?->name ?? '').($e->section ? " — {$e->section->name}" : '')),
                ]);
        }

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'holder_type' => ['required', Rule::in(['student', 'employee'])],
            'holder_id' => ['required', 'integer'],
            'card_uid' => ['required', 'string', 'max:32'],
            'issued_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        [$holder, $schoolId, $branchId] = $this->resolveHolder($data['holder_type'], (int) $data['holder_id']);

        $this->authorizeIssue($request, $schoolId, $branchId);

        $card = $this->issueCard(
            $holder,
            $schoolId,
            $branchId,
            $data['card_uid'],
            $data['issued_on'] ?? null,
            $data['note'] ?? null,
            $request->user()->id,
        );

        return response()->json([
            'data' => $this->row($card->load('holder')),
            'message' => 'Card issued.',
        ], 201);
    }

    /** Batch issuance — one branch, one holder type, up to 500 chips per call. */
    public function bulkStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'holder_type' => ['required', Rule::in(['student', 'employee'])],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.holder_id' => ['required', 'integer'],
            'rows.*.card_uid' => ['required', 'string', 'max:32'],
            'rows.*.issued_on' => ['nullable', 'date', 'after:2000-01-01', 'before:2100-01-01'],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $uids = collect($data['rows'])->map(fn (array $r) => strtoupper(trim($r['card_uid'])));

        if ($uids->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rows' => 'The same card UID appears more than once in the batch.',
            ]);
        }

        $issued = DB::transaction(function () use ($request, $data): int {
            $count = 0;

            foreach ($data['rows'] as $rowData) {
                [$holder, $schoolId, $branchId] = $this->resolveHolder($data['holder_type'], (int) $rowData['holder_id']);

                if ($branchId !== (int) $data['branch_id']) {
                    throw ValidationException::withMessages([
                        'rows' => "{$holder->full_name} does not belong to the selected branch.",
                    ]);
                }

                if ($count === 0) {
                    $this->authorizeIssue($request, $schoolId, $branchId);
                }

                $this->issueCard(
                    $holder,
                    $schoolId,
                    $branchId,
                    $rowData['card_uid'],
                    $rowData['issued_on'] ?? null,
                    $rowData['note'] ?? null,
                    $request->user()->id,
                );
                $count++;
            }

            return $count;
        });

        return response()->json([
            'message' => 'Cards issued.',
            'meta' => ['issued' => $issued],
        ], 201);
    }

    /**
     * School side: mark the card lost/damaged AND open a replacement request
     * in one move — scans reject from the next tap, Temari.et sees the
     * request in its fulfilment queue.
     */
    public function reportLost(Request $request, IdCard $card): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermissionForScope('cards.report', $card->school_id, $card->branch_id)
            || $request->user()->hasPlatformPermission('cards.manage'),
            403,
        );

        $data = $request->validate([
            'reason' => ['sometimes', Rule::in(['lost', 'damaged'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        abort_unless($card->status === 'active', 422, 'Only an active card can be reported.');

        $open = CardRequest::query()
            ->where('holder_type', $card->holder_type)
            ->where('holder_id', $card->holder_id)
            ->whereIn('status', CardRequest::OPEN_STATUSES)
            ->exists();
        abort_if($open, 422, 'A replacement request for this person is already in progress.');

        $cardRequest = DB::transaction(function () use ($request, $card, $data): CardRequest {
            $card->deactivate($data['reason'] ?? 'lost');

            return CardRequest::create([
                'school_id' => $card->school_id,
                'branch_id' => $card->branch_id,
                'id_card_id' => $card->id,
                'holder_type' => $card->holder_type,
                'holder_id' => $card->holder_id,
                'reason' => $data['reason'] ?? 'lost',
                'note' => $data['note'] ?? null,
                'requested_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'data' => ['request_id' => $cardRequest->id],
            'message' => 'Card reported — a replacement request has been sent to Temari.et.',
        ], 201);
    }

    /** Platform: revoke a card outright (leaver, misuse) — no request opened. */
    public function deactivate(Request $request, IdCard $card): JsonResponse
    {
        $this->authorizeIssue($request, $card->school_id, $card->branch_id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['lost', 'revoked'])],
        ]);

        abort_unless($card->status === 'active', 422, 'Only an active card can be deactivated.');

        $card->deactivate($data['status']);

        return response()->json([
            'data' => $this->row($card->refresh()->load('holder')),
            'message' => 'Card deactivated.',
        ]);
    }

    /** Platform: chain a replacement chip. Old card is retired FIRST (one-active-card rule). */
    public function replace(Request $request, IdCard $card): JsonResponse
    {
        $this->authorizeIssue($request, $card->school_id, $card->branch_id);

        $data = $request->validate([
            'card_uid' => ['required', 'string', 'max:32'],
        ]);

        $replacement = DB::transaction(function () use ($request, $card, $data): IdCard {
            // Deactivate before issuing — the partial unique index allows only
            // ONE active card per holder, so the order is load-bearing.
            if ($card->status === 'active') {
                $card->deactivate('replaced');
            }

            $replacement = $this->issueCard(
                $card->holder,
                $card->school_id,
                $card->branch_id,
                $data['card_uid'],
                null,
                null,
                $request->user()->id,
            );

            $card->update(['replaced_by_id' => $replacement->id]);

            return $replacement;
        });

        return response()->json([
            'data' => $this->row($replacement->load('holder')),
            'message' => 'Replacement card issued.',
        ], 201);
    }

    /** Shared issuance guts: normalize the UID, enforce uniqueness, create. */
    private function issueCard(
        Model $holder,
        int $schoolId,
        int $branchId,
        string $uid,
        ?string $issuedOn,
        ?string $note,
        int $issuedBy,
    ): IdCard {
        $uid = strtoupper(trim($uid));

        $this->assertUidFree($uid);
        $this->assertHolderFree($holder);

        return IdCard::create([
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'card_uid' => $uid,
            'holder_type' => $holder->getMorphClass(),
            'holder_id' => $holder->getKey(),
            'issued_on' => $issuedOn ?? Ethiopia::today(),
            'note' => $note,
            'issued_by' => $issuedBy,
        ]);
    }

    private function authorizeIssue(Request $request, ?int $schoolId, ?int $branchId): void
    {
        abort_unless(
            $request->user()->hasPlatformPermission('cards.manage')
            || $request->user()->hasPermissionForScope('cards.manage', $schoolId, $branchId),
            403,
        );
    }

    /**
     * @return array{0: Model, 1: int, 2: int}
     */
    private function resolveHolder(string $type, int $id): array
    {
        if ($type === 'employee') {
            $employee = Employee::findOrFail($id);

            return [$employee, $employee->school_id, $employee->branch_id];
        }

        $student = Student::with('currentEnrollment')->findOrFail($id);
        $branchId = $student->currentEnrollment?->branch_id ?? $student->branch_id;
        $schoolId = $student->currentEnrollment?->school_id ?? $student->school_id;

        if ($branchId === null || $schoolId === null) {
            throw ValidationException::withMessages([
                'holder_id' => 'This student has no branch to anchor the card to.',
            ]);
        }

        return [$student, $schoolId, $branchId];
    }

    private function assertUidFree(string $uid): void
    {
        $clash = IdCard::query()->where('card_uid', $uid)->where('status', 'active')->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'card_uid' => "Card {$uid} is already active for another person.",
            ]);
        }
    }

    private function assertHolderFree(Model $holder): void
    {
        $clash = IdCard::query()
            ->where('holder_type', $holder->getMorphClass())
            ->where('holder_id', $holder->getKey())
            ->where('status', 'active')
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages([
                'holder_id' => 'This person already has an active card — mark it lost or replace it instead.',
            ]);
        }
    }

    /** Correlated "holder already has an active card" subquery for candidates. */
    private function activeCardExists(string $morphClass, string $holderIdColumn): \Closure
    {
        return function ($q) use ($morphClass, $holderIdColumn): void {
            $q->select(DB::raw(1))
                ->from('id_cards')
                ->where('id_cards.holder_type', $morphClass)
                ->whereColumn('id_cards.holder_id', $holderIdColumn)
                ->where('id_cards.status', 'active')
                ->whereNull('id_cards.deleted_at');
        };
    }

    /** @return array<string, mixed> */
    private function row(IdCard $card): array
    {
        $holder = $card->holder;

        return [
            'id' => $card->id,
            'school_id' => $card->school_id,
            'school_name' => $card->school?->name,
            'branch_id' => $card->branch_id,
            'branch_name' => $card->branch?->name,
            'card_uid' => $card->card_uid,
            'holder_type' => $holder instanceof Employee ? 'employee' : 'student',
            'holder_id' => $card->holder_id,
            'holder_name' => $holder?->full_name,
            'status' => $card->status,
            'issued_on' => $card->issued_on,
            'note' => $card->note,
            'deactivated_at' => $card->deactivated_at?->toIso8601String(),
            'replaced_by_id' => $card->replaced_by_id,
            'issued_by_name' => $card->issuedBy?->name,
            'created_at' => $card->created_at?->toIso8601String(),
        ];
    }
}
