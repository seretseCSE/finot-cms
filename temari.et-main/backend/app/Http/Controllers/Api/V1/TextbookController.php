<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StockMovementType;
use App\Enums\TextbookLoanStatus;
use App\Http\Controllers\Concerns\HandlesBulkActions;
use App\Http\Controllers\Concerns\HandlesListQueries;
use App\Http\Controllers\Concerns\ResolvesInventoryScope;
use App\Http\Controllers\Controller;
use App\Http\Resources\TextbookLoanResource;
use App\Models\InventoryItem;
use App\Models\Section;
use App\Models\StudentEnrollment;
use App\Models\TextbookLoan;
use App\Services\Inventory\StockLedger;
use App\Services\Notify\Notifier;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * MoE textbook lending (phase 3): bulk-issue a book to a section (ONE
 * aggregate ledger issue carries the whole quantity — the atomic overdraw
 * check covers the batch), track per-student loans, take returns at year
 * end, and write off lost copies with the family told.
 */
class TextbookController extends Controller
{
    use HandlesBulkActions;
    use HandlesListQueries;
    use ResolvesInventoryScope;

    private const LIST_WITH = [
        'item:id,name',
        'student:id,first_name,father_name,grandfather_name,public_id',
        'section:id,name',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $school = $this->inventorySchool($request, ['inventory.view', 'inventory.manage']);
        $branch = $this->activeBranchOrNull($request);

        $query = TextbookLoan::query()
            ->where('school_id', $school->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($this->branchFilterId($request, $branch), fn ($q, int $id) => $q->where('branch_id', $id))
            ->when($request->integer('academic_year_id'), fn ($q, int $id) => $q->where('academic_year_id', $id))
            ->when($request->integer('inventory_item_id'), fn ($q, int $id) => $q->where('inventory_item_id', $id))
            ->when($request->integer('section_id'), fn ($q, int $id) => $q->where('section_id', $id))
            ->when($this->csvValues($request, 'status'), fn ($q, array $statuses) => $q->whereIn('status', $statuses))
            ->tap(fn ($q) => $this->applySearch($q, $request, fn ($w, string $n) => $w
                ->whereHas('student', fn ($s) => $s->where('search_text', 'ilike', $this->needle($n)))
                ->orWhereHas('item', fn ($i) => $i->where('name', 'ilike', $this->needle($n)))))
            ->with(self::LIST_WITH);

        if ($branch === null) {
            $query->with('branch:id,name');
        }

        $this->applySort($query, $request, ['created_at', 'status', 'returned_at'], 'created_at');

        return TextbookLoanResource::collection($query->paginate($this->perPage($request)));
    }

    /**
     * Issue one book to every ACTIVE student of a section (or a subset) —
     * skipping students who already hold an open copy, so re-running after
     * a partial issue never double-issues.
     */
    public function issue(Request $request): JsonResponse
    {
        $branch = $this->targetBranch($request);
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.manage', $branch->school_id, $branch->id),
            403,
        );

        $data = $request->validate([
            'academic_year_id' => [
                'required',
                Rule::exists('academic_years', 'id')->where('branch_id', $branch->id),
            ],
            'inventory_item_id' => [
                'required',
                Rule::exists('inventory_items', 'id')
                    ->where('school_id', $branch->school_id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'section_id' => [
                'required',
                Rule::exists('sections', 'id')->where('branch_id', $branch->id)->whereNull('deleted_at'),
            ],
            'quantity_per_student' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'student_ids' => ['nullable', 'array', 'max:200'],
            'student_ids.*' => ['integer'],
        ]);

        $perStudent = (int) ($data['quantity_per_student'] ?? 1);
        $item = InventoryItem::query()->findOrFail($data['inventory_item_id']);

        $enrollments = StudentEnrollment::query()
            ->where('branch_id', $branch->id)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('section_id', $data['section_id'])
            ->where('status', 'active')
            ->when($data['student_ids'] ?? null, fn ($q, array $ids) => $q->whereIn('student_id', $ids))
            ->with('student.guardians.parentProfile.user')
            ->get();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages([
                'section_id' => ['No active students found in this section.'],
            ]);
        }

        $alreadyOut = TextbookLoan::query()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('inventory_item_id', $item->id)
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->where('status', TextbookLoanStatus::Out)
            ->pluck('student_id')
            ->flip();

        $toIssue = $enrollments->filter(fn ($e) => ! $alreadyOut->has($e->student_id))->values();

        if ($toIssue->isEmpty()) {
            throw ValidationException::withMessages([
                'section_id' => ['Every selected student already holds this book.'],
            ]);
        }

        $sectionName = Section::query()->whereKey($data['section_id'])->value('name');

        $loans = DB::transaction(function () use ($toIssue, $data, $item, $branch, $user, $perStudent, $sectionName): array {
            // One aggregate ledger movement for the whole batch — the
            // overdraw check covers it atomically (all or nothing).
            app(StockLedger::class)->post(
                $branch->school_id,
                $branch->id,
                $item,
                StockMovementType::Issue,
                $toIssue->count() * $perStudent,
                ['recipient' => "{$sectionName} — textbooks"],
                $user->id,
            );

            $loans = [];
            foreach ($toIssue as $enrollment) {
                $loans[] = TextbookLoan::create([
                    'school_id' => $branch->school_id,
                    'branch_id' => $branch->id,
                    'academic_year_id' => $data['academic_year_id'],
                    'inventory_item_id' => $item->id,
                    'student_id' => $enrollment->student_id,
                    'section_id' => $enrollment->section_id,
                    'quantity' => $perStudent,
                    'status' => TextbookLoanStatus::Out,
                    'issued_by' => $user->id,
                ]);
            }

            return $loans;
        });

        // The family sees the book in the child's name (in-app only).
        $notifier = app(Notifier::class);
        foreach ($toIssue as $enrollment) {
            $notifier->toFamily($enrollment->student, 'inventory.textbook_issued', [
                'item' => $item->name,
            ], [
                'schoolId' => $branch->school_id,
                'branchId' => $branch->id,
                'dedupeKey' => "inventory.textbook_issued:{$enrollment->student_id}",
            ]);
        }

        ActivityLogger::log($user, 'inventory.textbooks_issued', $item, [
            'section_id' => (int) $data['section_id'],
            'issued' => count($loans),
            'skipped' => $enrollments->count() - count($loans),
        ], $branch->school_id, $branch->id);

        return response()->json([
            'data' => [
                'issued' => count($loans),
                'skipped' => $enrollments->count() - count($loans),
            ],
            'message' => 'Textbooks issued.',
        ], 201);
    }

    /** Year-end returns: mark loans returned, one ledger return per book. */
    public function returnLoans(Request $request): JsonResponse
    {
        $user = $request->user();

        // `ids` like every other bulk endpoint (HandlesBulkActions).
        $data = $request->validate(self::bulkIdRules());

        // Skip-and-report: an end-of-year collection is a hand-picked pile of
        // rows, and one already-returned or out-of-scope book must not stop the
        // shelf from being restocked.
        $skipped = [];
        $candidates = $this->bulkRows(
            $data['ids'],
            TextbookLoan::with(['item', 'student:id,first_name,father_name,grandfather_name']),
            $skipped,
        );

        $loans = collect();

        foreach ($candidates as $loan) {
            $name = $loan->student?->full_name;

            if ($loan->status !== TextbookLoanStatus::Out) {
                $skipped[] = self::skipRow($loan, $name, 'not_out');

                continue;
            }

            if (! $user->hasPermissionForScope('inventory.manage', $loan->school_id, $loan->branch_id)) {
                $skipped[] = self::skipRow($loan, $name, 'not_permitted');

                continue;
            }

            $loans->push($loan);
        }

        if ($loans->isEmpty()) {
            return response()->json([
                'data' => ['returned' => 0],
                'meta' => ['returned' => 0, 'requested' => count($data['ids']), 'skipped' => $skipped],
                'message' => 'No open loans matched.',
            ]);
        }

        $first = $loans->first();

        DB::transaction(function () use ($loans, $user): void {
            $ledger = app(StockLedger::class);

            // Aggregate per branch AND book title: 30 returned copies of one
            // title = one bin-card line, and stock is a per-branch balance, so
            // a school-wide sweep must never post another branch's copies here.
            foreach ($loans->groupBy(fn (TextbookLoan $l) => "{$l->branch_id}:{$l->inventory_item_id}") as $group) {
                $ledger->post(
                    $group->first()->school_id,
                    $group->first()->branch_id,
                    $group->first()->item,
                    StockMovementType::Return,
                    (float) $group->sum('quantity'),
                    ['recipient' => 'Textbook returns'],
                    $user->id,
                );
            }

            foreach ($loans as $loan) {
                $loan->update([
                    'status' => TextbookLoanStatus::Returned,
                    'returned_at' => now(),
                ]);
            }
        });

        ActivityLogger::log($user, 'inventory.textbooks_returned', $first, [
            'count' => $loans->count(),
        ], $first->school_id, $first->branch_id);

        return response()->json([
            'data' => ['returned' => $loans->count()],
            'meta' => [
                'returned' => $loans->count(),
                'requested' => count($data['ids']),
                'skipped' => $skipped,
            ],
            'message' => 'Books returned to the store.',
        ]);
    }

    /**
     * A copy is gone. NO ledger movement here: the issue already took the
     * copy off the shelf — a write-off on top would shrink stock twice. The
     * loan record is the loss trail; the family is told.
     */
    public function lost(Request $request, TextbookLoan $textbook): TextbookLoanResource
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermissionForScope('inventory.manage', $textbook->school_id, $textbook->branch_id),
            403,
        );

        if ($textbook->status !== TextbookLoanStatus::Out) {
            throw ValidationException::withMessages([
                'loan' => ['Only books still with the student can be marked lost.'],
            ]);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $textbook->update([
            'status' => TextbookLoanStatus::Lost,
            'lost_at' => now(),
            'note' => $data['note'] ?? null,
        ]);

        app(Notifier::class)->toFamily($textbook->student, 'inventory.textbook_lost', [
            'item' => (string) $textbook->item()->value('name'),
        ], [
            'schoolId' => $textbook->school_id,
            'branchId' => $textbook->branch_id,
        ]);

        ActivityLogger::log($user, 'inventory.textbook_lost', $textbook, [], $textbook->school_id, $textbook->branch_id);

        return (new TextbookLoanResource($textbook->load(self::LIST_WITH)))
            ->additional(['message' => 'Book marked lost.']);
    }
}
